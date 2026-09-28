<?php

namespace App\Statements;

use Carbon\CarbonImmutable;

final class StatementLine
{
    public function __construct(
        public readonly int $number,
        public readonly CarbonImmutable $date,
        public readonly string $description,
        public readonly ?string $bankType,
        /** negative is money out */
        public readonly int $amountCents,
        public readonly int $balanceCents,
    ) {}
}
