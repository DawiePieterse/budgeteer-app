<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Models\IngestedEmail;
use App\Models\Settlement;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class KeepFrom extends Command
{
    protected $signature = 'budgeteer:keep-from {date : First day to keep, for example 2026-07-01}
        {--household= : Household ID, when there is more than one}
        {--force : Do not ask for confirmation}';

    protected $description = 'Delete transactions and repayments dated before a day, and skip anything older from now on';

    public function handle(): int
    {
        $date = CarbonImmutable::parse((string) $this->argument('date'))->startOfDay();
        $households = Household::query()->when($this->option('household'), fn ($q, $id) => $q->whereKey($id))->get();
        if ($households->count() !== 1) {
            $this->error('Name the household with --household=ID ('.Household::query()->pluck('name', 'id')->map(fn ($n, $id) => "{$id}: {$n}")->implode(', ').').');

            return self::FAILURE;
        }
        $household = $households->first();

        $transactions = Transaction::withoutGlobalScopes()->where('household_id', $household->id)->where('posted_on', '<', $date->toDateString());
        $settlements = Settlement::withoutGlobalScopes()->where('household_id', $household->id)->where('received_on', '<', $date->toDateString());
        $this->line(sprintf('%s: keep from %s. This deletes %d transactions and %d repayments dated before it.',
            $household->name, $date->format('j F Y'), $transactions->count(), $settlements->count()));

        if (! $this->option('force') && ! $this->confirm('Delete them? This cannot be undone.')) {
            $this->line('Nothing deleted.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($household, $date, $transactions, $settlements) {
            $ids = $transactions->pluck('id');
            IngestedEmail::withoutGlobalScopes()->whereIn('transaction_id', $ids)
                ->update(['transaction_id' => null, 'status' => IngestedEmail::IGNORED, 'note' => 'Before the date Budgeteer keeps data from.']);
            $settlements->delete();
            Transaction::withoutGlobalScopes()->whereIn('transfer_pair_id', $ids)->update(['transfer_pair_id' => null]);
            Transaction::withoutGlobalScopes()->whereIn('id', $ids)->update(['transfer_pair_id' => null]);
            Transaction::withoutGlobalScopes()->whereIn('id', $ids)->delete();
            $household->update(['keep_from' => $date->toDateString()]);
        });

        $this->info('Done. Statement lines and bank emails dated before '.$date->format('j F Y').' are skipped from now on.');

        return self::SUCCESS;
    }
}
