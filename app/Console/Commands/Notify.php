<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Notify\Notifier;
use App\Notify\PushSender;
use Illuminate\Console\Command;

class Notify extends Command
{
    protected $signature = 'budgeteer:notify';

    protected $description = 'Send phone notifications for late or changed payments, budget warnings and stopped bank emails';

    public function handle(Notifier $notifier, PushSender $sender): int
    {
        if (! $sender->configured()) {
            $this->warn('No keys for phone notifications yet: run php artisan budgeteer:push-keys.');

            return self::SUCCESS;
        }
        foreach (Household::all() as $household) {
            $this->line(sprintf('%s: %d sent', $household->name, $notifier->run($household)));
        }

        return self::SUCCESS;
    }
}
