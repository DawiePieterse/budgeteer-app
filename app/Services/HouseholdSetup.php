<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\Household;

class HouseholdSetup
{
    /** @var list<string> */
    public const EXPENSE_CATEGORIES = [
        'Groceries', 'Eating out', 'Fuel and car', 'Medical', 'Insurance', 'Home and levies', 'Rates and services',
        'Household help', 'Clothing and shopping', 'Online shopping', 'Outings and entertainment',
        'Travel and accommodation', 'Subscriptions and internet', 'Giving', 'Cash', 'Bank fees', 'Personal care',
        'Gifts', 'Other',
    ];

    /** @var list<string> */
    public const INCOME_CATEGORIES = ['Consulting', 'Rental', 'Interest', 'Other income'];

    public function createDefaultCategories(Household $household): void
    {
        foreach ([CategoryKind::Expense->value => self::EXPENSE_CATEGORIES, CategoryKind::Income->value => self::INCOME_CATEGORIES] as $kind => $names) {
            foreach ($names as $sort => $name) {
                Category::withoutGlobalScopes()->firstOrCreate(
                    ['household_id' => $household->id, 'name' => $name],
                    ['kind' => $kind, 'sort' => $sort],
                );
            }
        }
    }
}
