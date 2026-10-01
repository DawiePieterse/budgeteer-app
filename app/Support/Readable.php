<?php

namespace App\Support;

/**
 * Bank text as people write it, for lists: "WOOLWORTHS CAVENDISH" becomes "Woolworths Cavendish".
 * Abbreviations ("IB", "PPS", "DSTV") and a few known ones like "ZA" stay in capitals, and small
 * joining words stay lower case. The bank's own text is still shown on the transaction's page.
 */
final class Readable
{
    private const CAPITALS = ['ATM', 'POS', 'EFT', 'ABSA', 'SARS', 'VAT', 'KFC', 'USA', 'CNA'];

    /** Two-letter words; any other two letters ("IB", "SA", "ZA") are an abbreviation. */
    private const TWO_LETTER_WORDS = ['AN', 'AS', 'AT', 'BE', 'BY', 'DO', 'GO', 'HE', 'IN', 'IS', 'IT', 'ME', 'MR', 'MS', 'MY', 'NO', 'OF', 'ON', 'OR', 'SO', 'ST', 'TO', 'UP', 'US', 'WE', 'DE', 'DR', 'EN', 'LA', 'LE'];

    /** Short words without vowels that are still written as words. */
    private const TITLES = ['MRS', 'JNR', 'SNR'];

    private const SMALL = ['and', 'of', 'the', 'to', 'from', 'for', 'at', 'in', 'on', 'by', 'van', 'der', 'den', 'en'];

    public static function text(?string $text): string
    {
        $text = trim((string) $text);
        // Text someone typed with its own mix of capitals is left as it is.
        if ($text === '' || $text !== mb_strtoupper($text)) {
            return $text;
        }

        $first = true;

        return (string) preg_replace_callback('/[\p{L}\p{N}][\p{L}\p{N}\'’.&]*/u', function (array $m) use (&$first): string {
            $word = $m[0];
            $isFirst = $first;
            $first = false;
            $letters = (string) preg_replace('/[^\p{L}]/u', '', $word);
            $abbreviation = mb_strlen($word) === 2 && mb_strlen($letters) === 2
                ? ! in_array($word, self::TWO_LETTER_WORDS, true)
                : mb_strlen($letters) >= 2 && mb_strlen($word) <= 4 && preg_match('/[AEIOUY]/u', $word) !== 1 && ! in_array($word, self::TITLES, true);
            if ($abbreviation || in_array($word, self::CAPITALS, true)) {
                return $word;
            }
            $lower = mb_strtolower($word);
            if (! $isFirst && in_array($lower, self::SMALL, true)) {
                return $lower;
            }

            return mb_convert_case($lower, MB_CASE_TITLE);
        }, $text);
    }
}
