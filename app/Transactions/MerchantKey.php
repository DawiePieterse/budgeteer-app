<?php

namespace App\Transactions;

/**
 * A short, stable name for who a transaction was with, used to group transactions and to
 * remember the category chosen for them. Branch, town, card and reference numbers are
 * dropped, so "WOOLWORTHS BELLVILLE" and "WOOLWORTHS CAPE TOWN" are both WOOLWORTHS, and a
 * debit order whose reference changes every month keeps one key.
 */
class MerchantKey
{
    /** Payment-provider prefixes in front of the real merchant name. */
    private const PREFIXES = ['YOCO', 'IK', 'PAYFAST', 'DL', 'ZAPPER1', 'SMC', 'EXPRESS', 'SNAPSCAN', 'PAYU', 'PEACH', 'INVESTECPB', 'REFUND'];

    /** Words too common to name a merchant on their own. */
    private const GENERIC = ['THE', 'CAFE', 'CC', 'CLUB', 'MR', 'MRS', 'MS', 'DR', 'NEW', 'SPAR', 'PNP', 'BP', 'CNR', 'DIE', 'VAN', 'DE', 'CAPE', 'SOUTH', 'NORTH', 'EAST', 'WEST', 'GRAND', 'INV'];

    /** Words that are never part of a merchant name. */
    private const NOISE = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC', 'MEI', 'OKT', 'DES', 'ZA', 'ZAR'];

    public function for(string $description): string
    {
        $text = strtoupper($description);
        $text = preg_replace('/\b(CARD\s*)?\.{2,}\d+\b/', ' ', $text) ?? $text;   // card ...1234
        $text = preg_replace('/\*+\d+/', ' ', $text) ?? $text;                    // *****6491
        $text = preg_replace('/\b\d+(\.\d+)?\s+[A-Z]{3}\b/', ' ', $text) ?? $text; // 250.00 KES
        $text = str_replace(['*', '#', '/', '\'', '"', ',', '(', ')', ':', '.', '>', '<'], ' ', $text);
        $text = preg_replace('/^INVESTECPB/', '', $text) ?? $text;
        $words = [];
        foreach (explode(' ', $text) as $word) {
            $word = trim($word, '-');
            if (preg_match('/^([A-Z-]{4,})\d+$/', $word, $m) === 1) {
                $word = $m[1];                  // DISCINSURE12345: a name with a reference glued on
            } elseif (preg_match('/\d/', $word) === 1) {
                continue;                       // references, branch codes, dates, times
            }
            if (strlen($word) >= 2 && ! in_array($word, self::NOISE, true)) {
                $words[] = $word;
            }
        }

        while (count($words) > 1 && in_array($words[0], self::PREFIXES, true)) {
            array_shift($words);
        }
        if ($words === []) {
            return 'UNKNOWN';
        }

        $first = $words[0];
        if (strlen($first) >= 4 && ! in_array($first, self::GENERIC, true)) {
            return $first;
        }

        return trim($first.' '.($words[1] ?? ''));
    }
}
