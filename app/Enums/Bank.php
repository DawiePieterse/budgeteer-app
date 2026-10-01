<?php

namespace App\Enums;

enum Bank: string
{
    case StandardBank = 'standard_bank';
    case Discovery = 'discovery';

    public function label(): string
    {
        return match ($this) {
            self::StandardBank => 'Standard Bank',
            self::Discovery => 'Discovery Bank',
        };
    }

    /** Two letters for the bank's tile in lists. */
    public function initials(): string
    {
        return match ($this) {
            self::StandardBank => 'SB',
            self::Discovery => 'DB',
        };
    }
}
