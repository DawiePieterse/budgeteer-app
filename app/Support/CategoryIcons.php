<?php

namespace App\Support;

/**
 * Simple line icons for budget lines (24×24, drawn with strokes), and a first guess of the icon from
 * a line's name. Drawn inline so they look the same on every phone and need no icon font.
 */
final class CategoryIcons
{
    /** name => [label, SVG path data] */
    public const ICONS = [
        'basket' => ['Groceries basket', 'M4 10h16l-1.6 9.2a1 1 0 0 1-1 .8H6.6a1 1 0 0 1-1-.8L4 10z M8 10l3-6 M16 10l-3-6 M9 14v3 M12 14v3 M15 14v3'],
        'cart' => ['Shopping cart', 'M3 4h2l2.4 11h10.2l2-8H6.3 M9 20a1 1 0 1 0 0-.01 M17 20a1 1 0 1 0 0-.01'],
        'car' => ['Car', 'M5 16v-4l2-5h10l2 5v4z M3 16h18v3h-3v-1H6v1H3z M7.5 13.5h.01 M16.5 13.5h.01'],
        'fuel' => ['Fuel pump', 'M5 20V5a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v15 M3 20h13 M5 10h9 M14 8l3 3v6a1.5 1.5 0 0 0 3 0V9l-3-3'],
        'bus' => ['Bus', 'M6 17V6a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v11z M6 11h12 M8 17v2 M16 17v2 M9 14h.01 M15 14h.01'],
        'heart' => ['Health', 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z M9.5 11h5 M12 8.5v5'],
        'shield' => ['Insurance', 'M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z M9 12l2 2 4-4'],
        'house' => ['Home', 'M4 11l8-7 8 7 M6 9.5V20h12V9.5 M10 20v-5h4v5'],
        'bolt' => ['Electricity and water', 'M13 3L5 13h6l-1 8 8-10h-6z'],
        'phone' => ['Phone', 'M8 3h8a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z M11 18h2'],
        'wifi' => ['Internet', 'M3 9a13 13 0 0 1 18 0 M6 12.5a8.5 8.5 0 0 1 12 0 M9 16a4 4 0 0 1 6 0 M12 19.5h.01'],
        'fork' => ['Eating out', 'M7 3v7a2 2 0 0 0 4 0V3 M9 10v11 M16 21V3c-2 1.5-3 4-3 7h3'],
        'coffee' => ['Coffee', 'M4 9h12v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5z M16 11h1.5a2 2 0 0 1 0 4H16 M8 3v3 M12 3v3'],
        'gift' => ['Gifts', 'M4 10h16v10H4z M3 7h18v3H3z M12 7v13 M12 7c-2-3-5-3-5-1s3 1 5 1c2 0 5 1 5-1s-3-2-5 1'],
        'shirt' => ['Clothes', 'M8 4l-5 3 2 4 3-1v10h8V10l3 1 2-4-5-3c-.5 1.5-2 2.5-4 2.5S8.5 5.5 8 4z'],
        'book' => ['Education', 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2z M4 21V5 M8 7h7'],
        'paw' => ['Pets', 'M12 13c3 0 5 3 5 5s-2 2-5 2-5 0-5-2 2-5 5-5z M6.5 10a1.5 2 0 1 0 0-.01 M17.5 10a1.5 2 0 1 0 0-.01 M9.5 6a1.5 2 0 1 0 0-.01 M14.5 6a1.5 2 0 1 0 0-.01'],
        'plane' => ['Travel', 'M3 13l7-1 4-8h2l-1.5 8 5.5-1 1 2-6.5 2L17 20h-2l-3.5-4.5L5 16z'],
        'ticket' => ['Outings', 'M3 8a2 2 0 0 0 0 4v0a2 2 0 0 1 0 4v2h18v-2a2 2 0 0 1 0-4 2 2 0 0 1 0-4V6H3z M14 6v12'],
        'dumbbell' => ['Sport', 'M4 9v6 M7 7v10 M17 7v10 M20 9v6 M7 12h10'],
        'cash' => ['Cash', 'M3 7h18v10H3z M12 12a2 2 0 1 0 0-.01 M6 10v4 M18 10v4'],
        'bank' => ['Bank fees', 'M3 9l9-5 9 5 M4 9h16 M6 9v8 M10 9v8 M14 9v8 M18 9v8 M3 20h18'],
        'chart' => ['Investments', 'M4 20V4 M4 20h16 M7 15l4-4 3 3 6-6 M16 8h4v4'],
        'document' => ['Admin and bookkeeping', 'M6 3h8l4 4v14H6z M14 3v4h4 M9 12h6 M9 16h6'],
        'wrench' => ['Repairs', 'M15 4a5 5 0 0 0-5 6.5L4 16.5 7.5 20l6-6A5 5 0 0 0 20 9l-3 3-3-1-1-3z'],
        'people' => ['Family', 'M9 11a3 3 0 1 0 0-.01 M3 20a6 6 0 0 1 12 0 M16 11a2.5 2.5 0 1 0 0-.01 M17 14.5a5 5 0 0 1 4 5.5'],
        'hands' => ['Giving', 'M12 7c-1-2-4-2-4 .5S12 12 12 12s4-2 4-4.5S13 5 12 7z M3 14l4-1 5 2 4-1 5 1 M3 18h5l4 2 9-3'],
        'sparkle' => ['Personal care', 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z M18 16l.8 2.2L21 19l-2.2.8L18 22l-.8-2.2L15 19l2.2-.8z'],
        'washer' => ['Laundry', 'M5 3h14v18H5z M5 7h14 M12 14a3.5 3.5 0 1 0 0-.01 M8 5h.01'],
        'leaf' => ['Garden', 'M5 19c0-8 5-14 15-14 0 10-6 15-14 15 M5 19l7-7'],
        'tv' => ['Subscriptions', 'M3 6h18v12H3z M8 21h8 M10 10l4 2-4 2z'],
        'tag' => ['Other', 'M3 12V4h8l10 10-8 8z M7.5 8.5h.01'],
    ];

    /** Words in a line's name that suggest an icon, most specific first. */
    private const GUESSES = [
        'bookkeep|accounting|admin|tax|sars' => 'document',
        'domestic|household help|cleaner' => 'people',
        'personal care|beauty|hair|salon|toiletr' => 'sparkle',
        'fuel|petrol|diesel' => 'fuel',
        'car\\b|cars\\b|vehicle|licen|tyre' => 'car',
        'grocer|food|woolworth|checkers|pick n pay|spar' => 'basket',
        'eating|restaurant|take ?away|dining' => 'fork',
        'coffee|cafe' => 'coffee',
        'medical|health|doctor|pharma|chemist|dentist|medicine' => 'heart',
        'insur|life cover|risk|pps|assurance' => 'shield',
        'levy|levies|bond|rent|home|house|municipal|rates' => 'house',
        'electric|water|utilit|eskom|prepaid' => 'bolt',
        'cell|phone|mobile|airtime|data' => 'phone',
        'internet|wifi|fibre|afrihost' => 'wifi',
        'subscri|netflix|dstv|showmax|streaming|spotify' => 'tv',
        'gift|present|birthday' => 'gift',
        'cloth|shoe|fashion' => 'shirt',
        'school|educat|study|course|book' => 'book',
        'pet|vet|dog|cat' => 'paw',
        'travel|holiday|flight|accommodation|trip' => 'plane',
        'outing|entertain|movie|leisure|fun' => 'ticket',
        'gym|sport|bowls|golf|fitness' => 'dumbbell',
        'cash|atm|withdraw' => 'cash',
        'bank|fee|charges' => 'bank',
        'invest|saving|retire|unit trust' => 'chart',
        'repair|mainten|hardware|diy' => 'wrench',
        'family|kids|child|son|daughter' => 'people',
        'church|giving|donat|charity|tithe' => 'hands',
        'laundry|washing|dry clean' => 'washer',
        'garden|plant|nursery' => 'leaf',
        'transport|bus|taxi|uber|toll' => 'bus',
        'shop|online|takealot|amazon' => 'cart',
    ];

    public static function guess(string $name): string
    {
        $name = mb_strtolower($name);
        foreach (self::GUESSES as $pattern => $icon) {
            if (preg_match('/\b(?:'.$pattern.')/u', $name) === 1) {
                return $icon;
            }
        }

        return 'tag';
    }

    public static function path(string $icon): string
    {
        return (self::ICONS[$icon] ?? self::ICONS['tag'])[1];
    }

    /** @return array<string, string> icon => label */
    public static function choices(): array
    {
        return array_map(fn (array $i) => $i[0], self::ICONS);
    }
}
