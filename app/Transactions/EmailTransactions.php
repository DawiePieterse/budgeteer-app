<?php

namespace App\Transactions;

use App\Enums\AccountKind;
use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Gmail\Parsers\ParsedEmail;
use App\Models\Account;
use App\Models\Card;
use App\Models\Category;
use App\Models\IngestedEmail;
use App\Models\Merchant;
use App\Models\Transaction;

/**
 * Records a transaction read from a bank email, or links the email to the same transaction
 * already imported from a statement. Purchases reach the statement a day or two after the
 * email, and the merchant name can differ, so they are matched on account, exact amount and
 * date, never on the name.
 */
class EmailTransactions
{
    /** Days a statement line may be dated after the email for the same purchase. */
    public const STATEMENT_LAG_DAYS = 4;

    public function __construct(private MerchantKey $merchantKey) {}

    /** @return array{0: Transaction, 1: bool} the transaction, and whether it was already there */
    public function record(ParsedEmail $email, int $householdId): array
    {
        $account = Account::withoutGlobalScopes()->firstOrCreate(
            ['household_id' => $householdId, 'bank' => $email->bank, 'number_ending' => $email->accountEnding],
            ['kind' => AccountKind::CreditCard, 'name' => 'Credit card'],
        );

        $card = null;
        if ($email->cardEnding !== null) {
            $card = Card::withoutGlobalScopes()->firstOrCreate(
                ['account_id' => $account->id, 'number_ending' => $email->cardEnding],
                ['household_id' => $householdId, 'holder_name' => $email->cardholder],
            );
            if ($card->holder_name === null && $email->cardholder !== null) {
                $card->update(['holder_name' => $email->cardholder]);
            }
        }

        $existing = $this->statementLine($email, $account);
        if ($existing !== null) {
            $existing->update(['card_id' => $card?->id, 'occurred_at' => $email->occurredAt]);

            return [$existing, true];
        }

        $key = $email->kind === TransactionKind::Cash ? 'CASH' : $this->merchantKey->for($email->description);
        $categoryId = $email->kind === TransactionKind::Cash
            ? Category::withoutGlobalScopes()->where('household_id', $householdId)->where('name', 'Cash')->value('id')
            : Merchant::withoutGlobalScopes()->where('household_id', $householdId)->where('key', $key)->value('category_id');

        $transaction = Transaction::create([
            'household_id' => $householdId,
            'account_id' => $account->id,
            'card_id' => $card?->id,
            'source' => TransactionSource::Email,
            'posted_on' => $email->occurredAt->toDateString(),
            'occurred_at' => $email->occurredAt,
            'description' => mb_substr($email->description, 0, 255),
            'merchant_key' => $key,
            'amount_cents' => $email->amountCents,
            'kind' => $email->kind,
            'category_id' => $categoryId,
        ]);

        return [$transaction, false];
    }

    /** A statement line for the same purchase that no email has claimed yet. */
    private function statementLine(ParsedEmail $email, Account $account): ?Transaction
    {
        return Transaction::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('source', TransactionSource::Statement)
            ->where('amount_cents', $email->amountCents)
            ->whereBetween('posted_on', [$email->occurredAt->subDay()->toDateString(), $email->occurredAt->addDays(self::STATEMENT_LAG_DAYS)->toDateString()])
            ->whereNotIn('id', IngestedEmail::withoutGlobalScopes()->whereNotNull('transaction_id')->select('transaction_id'))
            ->orderByRaw('abs(datediff(posted_on, ?))', [$email->occurredAt->toDateString()])
            ->first();
    }
}
