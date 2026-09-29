<?php

use App\Statements\Money;

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
