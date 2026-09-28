<?php

namespace App\Enums;

enum TransactionKind: string
{
    case Purchase = 'purchase';
    case Refund = 'refund';
    case DebitOrder = 'debit_order';
    case Payment = 'payment';
    case Deposit = 'deposit';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Fee = 'fee';
    case Interest = 'interest';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Refund => 'Refund',
            self::DebitOrder => 'Debit order',
            self::Payment => 'Payment',
            self::Deposit => 'Money in',
            self::Transfer => 'Transfer',
            self::Cash => 'Cash withdrawal',
            self::Fee => 'Bank fee',
            self::Interest => 'Interest',
        };
    }
}
