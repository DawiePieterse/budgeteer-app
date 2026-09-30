<?php

namespace App\Console\Commands;

use App\Enums\TransactionSource;
use App\Models\IngestedEmail;
use App\Models\Order;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Transactions\EmailTransactions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeDuplicates extends Command
{
    protected $signature = 'budgeteer:merge-duplicates
        {--force : Do not ask for confirmation}';

    protected $description = 'Merge a purchase saved from a bank email and again from a statement into one transaction';

    public function handle(): int
    {
        $pairs = $this->pairs();
        if ($pairs === []) {
            $this->info('No duplicates found.');

            return self::SUCCESS;
        }

        foreach ($pairs as [$email, $line]) {
            $this->line(sprintf('%s  R%s  email "%s" (%s)  +  statement "%s" (%s)', $email->account->name, number_format(abs($email->amount_cents) / 100, 2),
                $email->description, $email->posted_on->toDateString(), $line->description, $line->posted_on->toDateString()));
        }
        if (! $this->option('force') && ! $this->confirm('Merge these '.count($pairs).' into one transaction each? This cannot be undone.')) {
            $this->line('Nothing merged.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($pairs) {
            foreach ($pairs as [$email, $line]) {
                $this->merge($email, $line);
            }
        });
        $this->info('Merged '.count($pairs).'.');

        return self::SUCCESS;
    }

    /**
     * Each unconfirmed email transaction with the statement line it belongs to: the same account,
     * amount and merchant within the days the importer now allows.
     *
     * @return list<array{0: Transaction, 1: Transaction}>
     */
    private function pairs(): array
    {
        $claimed = [];
        $pairs = [];
        $emails = Transaction::withoutGlobalScopes()->with('account')
            ->where('source', TransactionSource::Email)->whereNull('statement_import_id')->orderBy('posted_on')->get();

        foreach ($emails as $email) {
            $line = Transaction::withoutGlobalScopes()
                ->where('account_id', $email->account_id)
                ->where('source', TransactionSource::Statement)
                ->where('amount_cents', $email->amount_cents)
                ->where('merchant_key', $email->merchant_key)
                ->whereNotIn('id', $claimed)
                ->whereNotIn('id', IngestedEmail::withoutGlobalScopes()->whereNotNull('transaction_id')->select('transaction_id'))
                ->whereBetween('posted_on', [
                    $email->posted_on->subDays(EmailTransactions::WIDE_LAG_DAYS)->toDateString(),
                    $email->posted_on->addDays(EmailTransactions::WIDE_LAG_DAYS)->toDateString(),
                ])
                ->orderByRaw('abs(datediff(posted_on, ?))', [$email->posted_on->toDateString()])
                ->first();
            if ($line !== null) {
                $claimed[] = $line->id;
                $pairs[] = [$email, $line];
            }
        }

        return $pairs;
    }

    /** Keep the statement line, with what the email knew (card, time, choices already made), and drop the email's copy. */
    private function merge(Transaction $email, Transaction $line): void
    {
        $line->update([
            'card_id' => $line->card_id ?? $email->card_id,
            'occurred_at' => $line->occurred_at ?? $email->occurred_at,
            'category_id' => $line->category_id ?? $email->category_id,
            'person_id' => $line->person_id ?? $email->person_id,
            'project_id' => $line->project_id ?? $email->project_id,
            'recurring_payment_id' => $line->recurring_payment_id ?? $email->recurring_payment_id,
        ]);
        IngestedEmail::withoutGlobalScopes()->where('transaction_id', $email->id)->update(['transaction_id' => $line->id]);
        if (! Order::withoutGlobalScopes()->where('transaction_id', $line->id)->exists()) {
            Order::withoutGlobalScopes()->where('transaction_id', $email->id)->update(['transaction_id' => $line->id]);
        }
        if (! Settlement::withoutGlobalScopes()->where('transaction_id', $line->id)->exists()) {
            Settlement::withoutGlobalScopes()->where('transaction_id', $email->id)->update(['transaction_id' => $line->id]);
        }
        Transaction::withoutGlobalScopes()->where('transfer_pair_id', $email->id)->update(['transfer_pair_id' => null]);
        $email->delete();
    }
}
