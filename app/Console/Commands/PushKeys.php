<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class PushKeys extends Command
{
    protected $signature = 'budgeteer:push-keys {--force : Replace keys that are already there (every phone must turn notifications on again)}';

    protected $description = 'Create the keys phone notifications are signed with, and save them in .env';

    public function handle(): int
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';
        if (preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env) && ! $this->option('force')) {
            $this->info('The keys are already in .env. Nothing changed.');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();
        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $line = $name.'='.$value;
            $env = preg_match("/^{$name}=.*$/m", $env)
                ? (string) preg_replace("/^{$name}=.*$/m", $line, $env)
                : rtrim($env, "\n")."\n".$line."\n";
        }
        file_put_contents($path, $env);

        $this->info('Keys saved in .env. Run php artisan config:cache next.');

        return self::SUCCESS;
    }
}
