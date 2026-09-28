<?php

namespace App\Console\Commands;

use App\Gmail\GmailSync;
use App\Models\GmailConnection;
use Illuminate\Console\Command;

class SyncGmail extends Command
{
    protected $signature = 'budgeteer:gmail-sync
        {--seconds=40 : Stop starting new messages after this long}
        {--retry : Read again the emails marked "Not read" or "Failed"}';

    protected $description = 'Read new bank emails from every linked Gmail account';

    public function handle(GmailSync $sync): int
    {
        $connections = GmailConnection::withoutGlobalScopes()->where('status', '!=', GmailConnection::NEEDS_RELINK)->get();
        foreach ($connections as $connection) {
            $result = $this->option('retry') ? $sync->retryUnread($connection) : $sync->sync($connection, (int) $this->option('seconds'));
            $this->line(sprintf('%s: %d added, %d matched to statements, %d other%s', $connection->email, $result['added'], $result['matched'], $result['other'], $result['finished'] ? '' : ' (more next time)'));
        }

        return self::SUCCESS;
    }
}
