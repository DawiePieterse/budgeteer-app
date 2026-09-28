<?php

namespace App\Gmail\Parsers;

use App\Enums\Bank;
use App\Enums\TransactionKind;
use Carbon\CarbonImmutable;

/** One transaction read from a bank notification email. */
final class ParsedEmail
{
    public function __construct(
        public readonly Bank $bank,
        public readonly TransactionKind $kind,
        public readonly string $description,
        /** negative is money out */
        public readonly int $amountCents,
        public readonly string $accountEnding,
        public readonly ?string $cardEnding,
        public readonly ?string $cardholder,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?int $availableBalanceCents = null,
    ) {}
}
