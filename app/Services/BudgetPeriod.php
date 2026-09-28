<?php

namespace App\Services;

use Carbon\CarbonImmutable;

/** A budget period runs from the household's start day of one month to the day before it the next month. */
final class BudgetPeriod
{
    public function __construct(public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    public static function containing(CarbonImmutable $date, int $startDay): self
    {
        $startDay = max(1, min(28, $startDay));
        $from = $date->day >= $startDay
            ? $date->setDay($startDay)->startOfDay()
            : $date->subMonthNoOverflow()->setDay($startDay)->startOfDay();

        return new self($from, $from->addMonthNoOverflow()->subDay());
    }

    public function previous(): self
    {
        return self::containing($this->from->subDay(), $this->from->day);
    }

    public function next(): self
    {
        return self::containing($this->to->addDay(), $this->from->day);
    }

    public function label(): string
    {
        return $this->from->day === 1
            ? $this->from->format('F Y')
            : $this->from->format('j M').' – '.$this->to->format('j M Y');
    }
}
