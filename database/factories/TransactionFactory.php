<?php

namespace Database\Factories;

use App\Enums\TransactionKind;
use App\Enums\TransactionSource;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Transaction> */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'household_id' => function (array $attributes) {
                $account = $attributes['account_id'];

                return ($account instanceof Account ? $account : Account::withoutGlobalScopes()->findOrFail($account))->household_id;
            },
            'source' => TransactionSource::Statement,
            'posted_on' => '2026-07-01',
            'description' => 'WOOLWORTHS BELLVILLE',
            'merchant_key' => 'WOOLWORTHS',
            'amount_cents' => -12345,
            'kind' => TransactionKind::Purchase,
        ];
    }
}
