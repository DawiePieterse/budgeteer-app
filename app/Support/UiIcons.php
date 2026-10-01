<?php

namespace App\Support;

/**
 * Line icons for the screens themselves (tabs, actions, statuses), drawn like the budget line icons:
 * 24×24 with strokes, inline, so they look the same on every phone and need no icon font.
 */
final class UiIcons
{
    public const PATHS = [
        'home' => 'M4 11l8-7 8 7 M6 9.5V20h12V9.5 M10 20v-5h4v5',
        'list' => 'M9 6h11 M9 12h11 M9 18h11 M4.5 6h.01 M4.5 12h.01 M4.5 18h.01',
        'inbox' => 'M4 13l2.5-8h11L20 13 M4 13v6h16v-6 M4 13h4.5l1 2.5h5l1-2.5H20',
        'pie' => 'M12 3a9 9 0 1 0 9 9h-9z M15 3.5A9 9 0 0 1 20.5 9H15z',
        'menu' => 'M4 7h16 M4 12h16 M4 17h16',
        'repeat' => 'M4 12a8 8 0 0 1 14-5.3 M18 3v4h-4 M20 12a8 8 0 0 1-14 5.3 M6 21v-4h4',
        'document' => 'M6 3h8l4 4v14H6z M14 3v4h4',
        'upload' => 'M6 3h8l4 4v14H6z M14 3v4h4 M12 17v-6 M9 14l3-3 3 3',
        'people' => 'M9 11a3 3 0 1 0 0-.01 M3 20a6 6 0 0 1 12 0 M16 11a2.5 2.5 0 1 0 0-.01 M17 14.5a5 5 0 0 1 4 5.5',
        'folder' => 'M3 6h6l2 2h10v11H3z',
        'gear' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19 12a7 7 0 0 0-.1-1.2l2-1.6-2-3.4-2.4 1a7 7 0 0 0-2-1.2L14 3h-4l-.5 2.6a7 7 0 0 0-2 1.2l-2.4-1-2 3.4 2 1.6a7 7 0 0 0 0 2.4l-2 1.6 2 3.4 2.4-1a7 7 0 0 0 2 1.2L10 21h4l.5-2.6a7 7 0 0 0 2-1.2l2.4 1 2-3.4-2-1.6c.1-.4.1-.8.1-1.2z',
        'card' => 'M3 6h18v12H3z M3 10h18 M7 15h4',
        'mail' => 'M3 6h18v12H3z M3 7l9 6 9-6',
        'bell' => 'M6 16V11a6 6 0 0 1 12 0v5l2 2H4z M10 21h4',
        'lock' => 'M6 11h12v9H6z M8 11V8a4 4 0 0 1 8 0v3',
        'shield' => 'M12 3l7 3v5c0 4.5-3 8-7 10-4-2-7-5.5-7-10V6z',
        'signout' => 'M15 4h4v16h-4 M10 8l-4 4 4 4 M6 12h10',
        'back' => 'M15 6l-6 6 6 6',
        'forward' => 'M9 6l6 6-6 6',
        'down' => 'M6 9l6 6 6-6',
        'close' => 'M6 6l12 12 M18 6L6 18',
        'plus' => 'M12 5v14 M5 12h14',
        'check' => 'M5 12l5 5 9-10',
        'alert' => 'M12 4l9 16H3z M12 10v4 M12 17h.01',
        'clock' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 7v5l3 2',
        'info' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M12 11v5 M12 8h.01',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z M20 20l-4-4',
        'external' => 'M14 4h6v6 M20 4l-9 9 M18 14v6H4V6h6',
        'chat' => 'M4 5h16v11H9l-5 4z',
        'transfer' => 'M7 7h13 M16 3l4 4-4 4 M17 17H4 M8 13l-4 4 4 4',
        'question' => 'M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14 M12 17.5h.01',
        'calendar' => 'M4 6h16v14H4z M4 10h16 M8 3v5 M16 3v5',
        'paste' => 'M9 4h6v3H9z M7 5H5v16h14V5h-2 M9 12h6 M9 16h4',
        'merge' => 'M6 4v6a4 4 0 0 0 4 4h8 M15 11l3 3-3 3 M6 20v-6',
        'trash' => 'M4 7h16 M9 7V4h6v3 M6 7l1 13h10l1-13',
        'restart' => 'M4 4v6h6 M5 15a8 8 0 1 0 2-8.5L4 10',
        'skip' => 'M5 12h14',
        'money' => 'M3 7h18v10H3z M12 12a2 2 0 1 0 0-.01 M6 10v4 M18 10v4',
    ];

    public static function path(string $name): string
    {
        return self::PATHS[$name] ?? self::PATHS['info'];
    }
}
