<?php

namespace App\Statements;

final class Money
{
    /** Matches "R 1,234.56", "R1,234.56", "-1,234.56", "-R564,550.73" and "1,234.56". */
    public const PATTERN = '/^(-)?\s*(-)?R?\s*(-)?([\d,]+\.\d{2})$/';

    public static function isAmount(string $text): bool
    {
        return preg_match(self::PATTERN, trim($text)) === 1;
    }

    public static function toCents(string $text): int
    {
        if (preg_match(self::PATTERN, trim($text), $m) !== 1) {
            throw new UnreadableStatement("Not an amount: {$text}");
        }
        $cents = (int) str_replace([',', '.'], '', $m[4]);

        return ($m[1] !== '' || $m[2] !== '' || $m[3] !== '') ? -$cents : $cents;
    }

    public static function format(int $cents): string
    {
        return ($cents < 0 ? '-' : '').'R'.number_format(abs($cents) / 100, 2, '.', ',');
    }
}
