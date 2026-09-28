<?php

namespace App\Gmail;

/** Turns an email's HTML into its visible lines of text, one per block, without markup. */
final class HtmlText
{
    private const BLOCKS = 'br|p|div|tr|td|th|li|h[1-6]|table|section|article|header|footer';

    /** @return list<string> */
    public static function lines(string $html): array
    {
        $html = preg_replace('#<(script|style|head)\b.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<(?:'.self::BLOCKS.')\b[^>]*>|</(?:'.self::BLOCKS.')>#i', "\n", $html) ?? $html;

        return self::plain(strip_tags($html));
    }

    /** @return list<string> */
    public static function plain(string $text): array
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{00A0}", "\u{200B}", "\r"], [' ', '', ''], $text);
        $lines = array_map(fn (string $l) => trim(preg_replace('/[ \t]+/u', ' ', $l) ?? $l), explode("\n", $text));

        return array_values(array_filter($lines, fn (string $l) => $l !== ''));
    }
}
