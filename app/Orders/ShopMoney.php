<?php

namespace App\Orders;

/** Amounts as shops write them: "R 1 299.00", "R1,299.00", "539.1 ZAR". */
final class ShopMoney
{
    public static function cents(string $text): ?int
    {
        $text = trim(str_replace("\u{00A0}", ' ', $text));
        if (! preg_match('/^(?:R\s?)?(\d{1,3}(?:[ ,]\d{3})*|\d+)(?:\.(\d{1,2}))?(?:\s?ZAR)?$/', $text, $m)) {
            return null;
        }
        $rand = (int) str_replace([' ', ','], '', $m[1]);

        return $rand * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
