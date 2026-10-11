<?php

namespace App\Support;

use App\Models\CategoryGroup;

/**
 * Colours for the household's groups of budget lines: Apple's system colours, as on the desktop Overview.
 * Blue, green and red are left out: they mean spending, money in and over budget there. Pink comes last, as
 * it is the nearest to red. The group's name is always written next to its colour.
 */
final class GroupColours
{
    /** Handed out in this order. */
    public const CHOICES = [
        'indigo' => 'Indigo',
        'orange' => 'Orange',
        'teal' => 'Teal',
        'purple' => 'Purple',
        'yellow' => 'Yellow',
        'brown' => 'Brown',
        'mint' => 'Mint',
        'pink' => 'Pink',
    ];

    /** The first colour no other group in the household has yet, or the next in turn when all are used. */
    public static function next(int $householdId): string
    {
        $used = CategoryGroup::withoutGlobalScopes()->where('household_id', $householdId)->pluck('colour')->all();
        foreach (array_keys(self::CHOICES) as $colour) {
            if (! in_array($colour, $used, true)) {
                return $colour;
            }
        }

        return array_keys(self::CHOICES)[count($used) % count(self::CHOICES)];
    }
}
