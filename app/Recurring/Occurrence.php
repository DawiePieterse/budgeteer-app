<?php

namespace App\Recurring;

use App\Models\RecurringPayment;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/** One expected payment on one date, and what happened to it. */
final class Occurrence
{
    public const PAID = 'paid';

    public const CHANGED = 'changed';

    public const DUE = 'due';

    public const LATE = 'late';

    public const SKIPPED = 'skipped';

    public const PAID_BY_HAND = 'paid_by_hand';

    public function __construct(
        public readonly RecurringPayment $payment,
        public readonly CarbonImmutable $dueOn,
        public readonly string $status,
        public readonly ?Transaction $transaction = null,
    ) {}

    public function needsAttention(): bool
    {
        return in_array($this->status, [self::LATE, self::CHANGED], true);
    }

    public function label(): string
    {
        return match ($this->status) {
            self::PAID => 'Paid',
            self::CHANGED => 'Amount changed',
            self::DUE => 'Due',
            self::LATE => 'Late',
            self::SKIPPED => 'Skipped',
            self::PAID_BY_HAND => 'Paid (marked by hand)',
        };
    }
}
