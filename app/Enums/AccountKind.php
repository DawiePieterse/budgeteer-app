<?php

namespace App\Enums;

enum AccountKind: string
{
    case Cheque = 'cheque';
    case CreditCard = 'credit_card';
    case MoneyMarket = 'money_market';

    public function label(): string
    {
        return match ($this) {
            self::Cheque => 'Cheque account',
            self::CreditCard => 'Credit card',
            self::MoneyMarket => 'Money market',
        };
    }
}
