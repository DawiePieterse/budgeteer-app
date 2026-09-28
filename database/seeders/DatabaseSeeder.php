<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdSetup;
use Illuminate\Database\Seeder;

/** Development data only: one household with one user, signed in with the dev login. */
class DatabaseSeeder extends Seeder
{
    public function run(HouseholdSetup $setup): void
    {
        $household = Household::create(['name' => 'Test household', 'period_start_day' => 1]);
        $setup->createDefaultCategories($household);
        User::create(['household_id' => $household->id, 'name' => 'Test User', 'email' => 'test@example.com']);
    }
}
