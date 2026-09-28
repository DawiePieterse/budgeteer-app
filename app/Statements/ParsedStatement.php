<?php

namespace App\Statements;

use App\Enums\AccountKind;
use App\Enums\Bank;
use Carbon\CarbonImmutable;

final class ParsedStatement
{
    /** @param list<StatementLine> $lines */
    public function __construct(
        public readonly Bank $bank,
        public readonly AccountKind $accountKind,
        public readonly string $accountNumberEnding,
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly int $openingCents,
        public readonly int $closingCents,
        public readonly array $lines,
        /** totals printed on the statement, where it prints them */
        public readonly ?int $printedMoneyOutCents = null,
        public readonly ?int $printedMoneyInCents = null,
    ) {}

    public function moneyOutCents(): int
    {
        return array_sum(array_map(fn (StatementLine $l) => min($l->amountCents, 0), $this->lines));
    }

    public function moneyInCents(): int
    {
        return array_sum(array_map(fn (StatementLine $l) => max($l->amountCents, 0), $this->lines));
    }

    /** Identifies the statement, so the same one is not imported twice. */
    public function fingerprint(): string
    {
        $parts = [$this->bank->value, $this->accountNumberEnding, $this->from->toDateString(), $this->to->toDateString(), $this->openingCents, $this->closingCents, count($this->lines)];

        return hash('sha256', implode('|', $parts));
    }
}
