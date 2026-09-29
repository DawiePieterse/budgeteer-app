<?php

namespace App\Orders;

use Carbon\CarbonImmutable;

/** What a shop's order confirmation email says. */
final class ParsedOrder
{
    /** @param list<array{name: string, quantity: int, price_cents: int|null}> $items */
    public function __construct(
        public readonly string $shop,
        public readonly string $orderNumber,
        public readonly CarbonImmutable $orderedAt,
        public readonly int $totalCents,
        public readonly array $items,
        public readonly ?string $deliverTo = null,
    ) {}
}
