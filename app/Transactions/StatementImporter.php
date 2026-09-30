<?php

namespace App\Transactions;

use App\Enums\AccountKind;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Merchant;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Models\User;
use App\Orders\OrderMatcher;
use App\Recurring\RecurringMatcher;
use App\Statements\ParsedStatement;
use App\Statements\StatementLine;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StatementImporter
{
    /** Merchant keys for transactions that are not with a merchant. */
    private const KIND_KEYS = [
        TransactionKind::Fee->value => 'BANK FEES',
        TransactionKind::Cash->value => 'CASH',
        TransactionKind::Interest->value => 'INTEREST',
        TransactionKind::Transfer->value => 'OWN ACCOUNTS',
    ];

    /** Categories filled in without asking, by kind. */
    private const KIND_CATEGORIES = [
        TransactionKind::Fee->value => 'Bank fees',
        TransactionKind::Cash->value => 'Cash',
    ];

    public function __construct(
        private Classifier $classifier,
        private MerchantKey $merchantKey,
        private TransferPairer $pairer,
        private RecurringMatcher $recurring,
        private OrderMatcher $orderMatcher,
    ) {}

    public function alreadyImported(ParsedStatement $statement, Household $household): ?StatementImport
    {
        return StatementImport::withoutGlobalScopes()
            ->where('household_id', $household->id)
            ->where('fingerprint', $statement->fingerprint())
            ->first();
    }

    public function existingAccount(ParsedStatement $statement, Household $household): ?Account
    {
        return Account::withoutGlobalScopes()
            ->where('household_id', $household->id)
            ->where('bank', $statement->bank)
            ->where('number_ending', $statement->accountNumberEnding)
            ->first();
    }

    /** How many of the statement's lines are already saved, from an earlier overlapping statement. */
    public function countAlreadyThere(ParsedStatement $statement, Household $household): int
    {
        $account = $this->existingAccount($statement, $household);

        return $account === null ? 0 : count($statement->lines) - count($this->newLines($statement, $account));
    }

    public function import(ParsedStatement $statement, User $user): StatementImport
    {
        $household = $user->household;
        if ($this->alreadyImported($statement, $household) !== null) {
            throw new RuntimeException('This statement has already been imported.');
        }

        return DB::transaction(function () use ($statement, $user, $household) {
            $account = $this->existingAccount($statement, $household) ?? Account::create([
                'household_id' => $household->id,
                'bank' => $statement->bank,
                'kind' => $statement->accountKind,
                'name' => $this->accountName($statement),
                'number_ending' => $statement->accountNumberEnding,
            ]);

            $new = $this->newLines($statement, $account);
            $tooOld = 0;
            if ($household->keep_from !== null) {
                $kept = array_values(array_filter($new, fn (StatementLine $l) => $l->date->greaterThanOrEqualTo($household->keep_from)));
                $tooOld = count($new) - count($kept);
                $new = $kept;
            }
            $import = StatementImport::create([
                'household_id' => $household->id,
                'account_id' => $account->id,
                'user_id' => $user->id,
                'period_from' => $statement->from,
                'period_to' => $statement->to,
                'opening_cents' => $statement->openingCents,
                'closing_cents' => $statement->closingCents,
                'lines' => count($statement->lines),
                'added' => count($new),
                'already_there' => count($statement->lines) - count($new) - $tooOld,
                'fingerprint' => $statement->fingerprint(),
            ]);

            $categories = Category::withoutGlobalScopes()->where('household_id', $household->id)->pluck('id', 'name');
            $merchants = Merchant::withoutGlobalScopes()->where('household_id', $household->id)->whereNotNull('category_id')->pluck('category_id', 'key');
            $projects = Merchant::withoutGlobalScopes()->where('household_id', $household->id)->whereNotNull('project_id')->pluck('project_id', 'key');
            $ownNames = $household->ownAccountNames();

            $matchedEmails = 0;
            foreach ($new as $line) {
                $fromEmail = $this->emailTransaction($account, $line);
                if ($fromEmail !== null) {
                    // Already read from the bank's email: the statement confirms it and its wording wins,
                    // so a later overlapping statement recognises the line.
                    $fromEmail->update([
                        'statement_import_id' => $import->id,
                        'posted_on' => $line->date,
                        'description' => mb_substr($line->description, 0, 255),
                        'bank_type' => $line->bankType,
                        'balance_after_cents' => $line->balanceCents,
                        'line_on_statement' => $line->number,
                    ]);
                    $matchedEmails++;

                    continue;
                }

                $kind = $this->classifier->kind($statement->bank, $line->bankType, $line->description, $line->amountCents, $ownNames[0] ?? '');
                if (in_array($kind, [TransactionKind::Payment, TransactionKind::Deposit], true) && $this->isOwnName($line->description, $ownNames)) {
                    $kind = TransactionKind::Transfer;
                }
                $key = self::KIND_KEYS[$kind->value] ?? $this->merchantKey->for($line->description);
                $categoryName = self::KIND_CATEGORIES[$kind->value] ?? ($kind === TransactionKind::Interest && $line->amountCents > 0 ? 'Interest' : null);

                Transaction::create([
                    'household_id' => $household->id,
                    'account_id' => $account->id,
                    'source' => TransactionSource::Statement,
                    'statement_import_id' => $import->id,
                    'posted_on' => $line->date,
                    'description' => mb_substr($line->description, 0, 255),
                    'bank_type' => $line->bankType,
                    'merchant_key' => $key,
                    'amount_cents' => $line->amountCents,
                    'kind' => $kind,
                    'is_transfer' => $kind === TransactionKind::Transfer,
                    // Remembered choice first; fees, cash and interest fall back to their starter category.
                    'category_id' => $kind === TransactionKind::Transfer ? null : ($merchants[$key] ?? ($categoryName !== null ? $categories[$categoryName] ?? null : null)),
                    'project_id' => $kind === TransactionKind::Transfer ? null : ($projects[$key] ?? null),
                    'balance_after_cents' => $line->balanceCents,
                    'line_on_statement' => $line->number,
                    'updated_by' => $user->id,
                ]);
            }

            $import->update(['added' => count($new) - $matchedEmails, 'matched_emails' => $matchedEmails]);

            if ($account->statement_balance_on === null || $statement->to->greaterThanOrEqualTo($account->statement_balance_on)) {
                $account->update(['statement_balance_cents' => $statement->closingCents, 'statement_balance_on' => $statement->to]);
            }

            $this->pairer->pair($household->id);
            $this->recurring->link($household->id);
            $this->orderMatcher->link($household->id);

            return $import;
        });
    }

    /** A transaction read from an email for the same purchase, not yet confirmed by a statement. */
    private function emailTransaction(Account $account, StatementLine $line): ?Transaction
    {
        return $this->emailTransactionBetween($account, $line, EmailTransactions::STATEMENT_LAG_DAYS, 1)
            ?? $this->emailTransactionBetween($account, $line, EmailTransactions::WIDE_LAG_DAYS, EmailTransactions::WIDE_LAG_DAYS, $this->merchantKey->for($line->description));
    }

    /** Within the days around the line; with a merchant key, only an email with that same merchant. */
    private function emailTransactionBetween(Account $account, StatementLine $line, int $before, int $after, ?string $merchantKey = null): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('source', TransactionSource::Email)
            ->whereNull('statement_import_id')
            ->where('amount_cents', $line->amountCents)
            ->when($merchantKey !== null, fn ($q) => $q->where('merchant_key', $merchantKey))
            ->whereBetween('posted_on', [$line->date->subDays($before)->toDateString(), $line->date->addDays($after)->toDateString()])
            ->orderByRaw('abs(datediff(posted_on, ?))', [$line->date->toDateString()])
            ->first();
    }

    /**
     * The lines not saved yet. Overlapping statements repeat lines, and one statement can have
     * two identical lines (two R20 parking payments on one day), so lines are counted, not just
     * looked up.
     *
     * @return list<StatementLine>
     */
    private function newLines(ParsedStatement $statement, Account $account): array
    {
        $existing = Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->whereBetween('posted_on', [$statement->from->subDay(), $statement->to->addDay()])
            ->get(['posted_on', 'amount_cents', 'description'])
            ->countBy(fn (Transaction $t) => $this->lineKey($t->posted_on->toDateString(), $t->amount_cents, $t->description))
            ->all();

        $new = [];
        foreach ($statement->lines as $line) {
            $key = $this->lineKey($line->date->toDateString(), $line->amountCents, $line->description);
            if (($existing[$key] ?? 0) > 0) {
                $existing[$key]--;

                continue;
            }
            $new[] = $line;
        }

        return $new;
    }

    private function lineKey(string $date, int $cents, string $description): string
    {
        return $date.'|'.$cents.'|'.mb_substr($description, 0, 255);
    }

    /** @param list<string> $ownNames */
    private function isOwnName(string $description, array $ownNames): bool
    {
        $d = strtoupper(trim($description));
        foreach ($ownNames as $name) {
            if ($name !== '' && str_starts_with($d, $name)) {
                return true;
            }
        }

        return false;
    }

    private function accountName(ParsedStatement $statement): string
    {
        return match ($statement->accountKind) {
            AccountKind::Cheque => 'Cheque account',
            AccountKind::CreditCard => 'Credit card',
            AccountKind::MoneyMarket => 'Money market',
        };
    }
}
