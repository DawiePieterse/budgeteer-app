<?php

use App\Statements\Money;
use App\Support\Readable;

if (! function_exists('money')) {
    /** R1,234.56 from cents, for views. */
    function money(int $cents): string
    {
        return Money::format($cents);
    }
}

if (! function_exists('versioned_asset')) {
    /** The asset's URL with its last change time, so browsers fetch a new copy after every update. */
    function versioned_asset(string $path): string
    {
        $file = public_path($path);

        return asset($path).'?v='.(is_file($file) ? filemtime($file) : '0');
    }
}

if (! function_exists('rand_whole')) {
    /** R4,200 from cents, rounded to the rand, where space is short. */
    function rand_whole(int $cents): string
    {
        return ($cents < 0 ? '-' : '').'R'.number_format((int) round(abs($cents) / 100), 0, '.', ',');
    }
}

if (! function_exists('readable')) {
    /** Bank text in sentence-style capitals for lists: "WOOLWORTHS CAVENDISH" as "Woolworths Cavendish". */
    function readable(?string $text): string
    {
        return Readable::text($text);
    }
}
