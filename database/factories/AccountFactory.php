<?php

namespace Database\Factories;

use App\Enums\AccountKind;
use App\Enums\Bank;
use App\Models\Account;
use App\Models\Household;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'household_id' => Household::factory(),
            'bank' => Bank::StandardBank,
            'kind' => AccountKind::Cheque,
            'name' => 'Cheque account',
            'number_ending' => (string) fake()->unique()->numberBetween(1000, 9999),
        ];
    }

    public function discovery(): static
    {
        return $this->state(['bank' => Bank::Discovery, 'kind' => AccountKind::CreditCard, 'name' => 'Credit card']);
    }
}
