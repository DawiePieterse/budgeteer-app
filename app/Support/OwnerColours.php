<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Project;

/**
 * Colours that show whose a transaction is: green for the household's own budget, and one colour for
 * each person who pays back and each special project. From the validated categorical palette; red is
 * left out because it means "over budget". The name is always written next to the colour.
 */
final class OwnerColours
{
    public const HOUSEHOLD = 'green';

    /** Handed out in this order, so the first person (usually a child's card) is pink. */
    public const CHOICES = [
        'pink' => 'Pink',
        'blue' => 'Blue',
        'orange' => 'Orange',
        'aqua' => 'Aqua',
        'violet' => 'Violet',
        'yellow' => 'Yellow',
    ];

    /** The first colour no one else in the household has yet, or the next in turn when all are used. */
    public static function next(int $householdId): string
    {
        $used = [
            ...Person::withoutGlobalScopes()->where('household_id', $householdId)->pluck('colour')->all(),
            ...Project::withoutGlobalScopes()->where('household_id', $householdId)->pluck('colour')->all(),
        ];
        foreach (array_keys(self::CHOICES) as $colour) {
            if (! in_array($colour, $used, true)) {
                return $colour;
            }
        }

        return array_keys(self::CHOICES)[count($used) % count(self::CHOICES)];
    }
}
