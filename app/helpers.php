<?php

use App\Statements\Money;

if (! function_exists('money')) {
    /** R1,234.56 from cents, for views. */
    function money(int $cents): string
    {
        return Money::format($cents);
    }
}
