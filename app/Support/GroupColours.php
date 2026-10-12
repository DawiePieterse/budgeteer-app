<?php

namespace App\Support;

use App\Models\CategoryGroup;

/**
 * Colours for the household's groups of budget lines: Apple's system colours, as on the desktop Overview,
 * stepped where needed so neighbours stay apart for colour-blind readers too (checked with the dataviz
 * palette validator, light and dark; the first four are apart from each other as well). Blue, green and
 * red are left out: they mean spending, money in and over budget there. The group's name is always
 * written next to its colour.
 */
final class GroupColours
{
    /** Handed out in this order. */
    public const CHOICES = [
        'indigo' => 'Indigo',
        'orange' => 'Orange',
        'teal' => 'Teal',
        'pink' => 'Pink',
        'yellow' => 'Yellow',
        'brown' => 'Brown',
        'mint' => 'Mint',
        'purple' => 'Purple',
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
