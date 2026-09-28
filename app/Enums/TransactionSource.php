<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Statement = 'statement';
    case Email = 'email';
    case Manual = 'manual';
}
