<?php

namespace App\Console\Commands;

use App\Gmail\GmailSync;
use App\Models\GmailConnection;
use App\Models\IngestedEmail;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncGmail extends Command
{
    protected $signature = 'budgeteer:gmail-sync
        {--seconds=40 : Stop starting new messages after this long}
        {--retry : Read again the emails marked "Not read" or "Failed"}
        {--since= : Also read every labelled email from this day, for example 2026-07-01}
        {--list-unread : Show the emails that were not turned into transactions, and why}';

    protected $description = 'Read new bank emails from every linked Gmail account';

    public function handle(GmailSync $sync): int
    {
        $connections = GmailConnection::withoutGlobalScopes()->where('status', '!=', GmailConnection::NEEDS_RELINK)->get();
        if ($this->option('list-unread')) {
            $rows = IngestedEmail::withoutGlobalScopes()->whereIn('gmail_connection_id', $connections->pluck('id'))
                ->whereNotIn('status', [IngestedEmail::ADDED, IngestedEmail::MATCHED, IngestedEmail::ORDER])->orderBy('received_at')
                ->get()->map(fn (IngestedEmail $e) => [$e->received_at?->format('Y-m-d H:i'), $e->status, mb_substr((string) $e->subject, 0, 50), $e->note]);
            $this->table(['Received', 'Status', 'Subject', 'Why'], $rows->all());

            return self::SUCCESS;
        }
        foreach ($connections as $connection) {
            $result = match (true) {
                $this->option('since') !== null => $sync->readSince($connection, CarbonImmutable::parse((string) $this->option('since')), (int) $this->option('seconds') ?: 600),
                (bool) $this->option('retry') => $sync->retryUnread($connection),
                default => $sync->sync($connection, (int) $this->option('seconds')),
            };
            $this->line(sprintf('%s: %d added, %d matched to statements, %d other%s', $connection->email, $result['added'], $result['matched'], $result['other'], $result['finished'] ? '' : ' (more next time)'));
        }

        return self::SUCCESS;
    }
}
