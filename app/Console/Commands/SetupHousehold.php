<?php

namespace App\Console\Commands;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdSetup;
use Illuminate\Console\Command;

class SetupHousehold extends Command
{
    protected $signature = 'budgeteer:setup
        {--name= : Household name (default BUDGETEER_HOUSEHOLD_NAME)}
        {--emails= : Comma-separated Google addresses allowed to sign in (default BUDGETEER_ALLOWED_EMAILS)}';

    protected $description = 'Create the household, its default categories and the people allowed to sign in';

    public function handle(HouseholdSetup $setup): int
    {
        $name = (string) ($this->option('name') ?: config('budgeteer.household_name'));
        $emails = array_values(array_filter(array_map(
            fn (string $e) => strtolower(trim($e)),
            explode(',', (string) ($this->option('emails') ?: config('budgeteer.allowed_emails'))),
        )));
        if ($emails === []) {
            $this->error('Give at least one Google address with --emails or BUDGETEER_ALLOWED_EMAILS.');

            return self::FAILURE;
        }

        $household = Household::firstOrCreate(['name' => $name]);
        $setup->createDefaultCategories($household);

        foreach ($emails as $email) {
            $user = User::firstOrCreate(['email' => $email], ['household_id' => $household->id, 'name' => $email]);
            if ($user->household_id !== $household->id) {
                $this->warn("{$email} already belongs to another household; left as it is.");

                continue;
            }
            $this->info("{$email} can sign in.");
        }

        return self::SUCCESS;
    }
}
