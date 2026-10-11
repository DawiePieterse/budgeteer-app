<?php

namespace App\Services;

/**
 * Reads a budget pasted from a spreadsheet or document: one line per item, the amount last. A line with
 * words and no amount starts a group, and the lines under it go into that group.
 *
 *   Home and bills
 *   Levies Vygeboom  R2 807,44
 *   Food
 *   Everyday food items & household basics    10000
 *   🍽️ Eating Out & Takeaways	2000
 */
final class BudgetList
{
    /** Column headings and totals in a pasted budget, not budget lines or groups. */
    private const NOT_LINES = ['bedrag', 'amount', 'item', 'items', 'total', 'totaal', 'budget', 'begroting', 'category', 'kategorie', 'name', 'naam', 'monthly', 'maandeliks'];

    /** @return list<array{name: string, cents: int, group: string|null}> */
    public static function parse(string $text): array
    {
        $items = [];
        $group = null;
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);
            // The amount is the last number on the line: "10000", "2807.44", "R1,200", "2 807,44".
            if (preg_match('/^(.*?)[\s\t]+R?\s*(\d{1,3}(?:[ ,\x{00A0}]\d{3})*(?:[.,]\d{1,2})?|\d+(?:[.,]\d{1,2})?)\s*$/u', $line, $m) !== 1) {
                // Words without an amount head a group; a heading with no lines under it changes nothing.
                $heading = self::cleanName($line);
                if (preg_match('/\p{L}/u', $heading) === 1 && ! self::isColumnHeading($heading)) {
                    $group = mb_substr($heading, 0, 60);
                }

                continue;
            }
            $name = self::cleanName($m[1]);
            if ($name === '' || self::isColumnHeading($name)) {
                continue;
            }
            $items[] = ['name' => $name, 'cents' => self::cents($m[2]), 'group' => $group];
        }

        return $items;
    }

    /** "Bedrag", "Item Amount", "Total": every word a column heading or a total. */
    private static function isColumnHeading(string $text): bool
    {
        $words = preg_split('/[\s\/]+/u', mb_strtolower($text)) ?: [];

        return array_diff($words, self::NOT_LINES) === [];
    }

    private static function cleanName(string $name): string
    {
        // Drop emoji and other symbols in front ("🍽️ Eating Out"), and spaces or tabs around it.
        $name = preg_replace('/^[^\p{L}\p{N}]+/u', '', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    }

    private static function cents(string $amount): int
    {
        $amount = str_replace(["\u{00A0}", ' '], '', $amount);
        // A comma or dot followed by one or two digits at the end is the decimal mark.
        if (preg_match('/^(.*)[.,](\d{1,2})$/', $amount, $m) === 1) {
            return (int) str_replace([',', '.'], '', $m[1]) * 100 + (int) str_pad($m[2], 2, '0');
        }

        return (int) str_replace([',', '.'], '', $amount) * 100;
    }
}
