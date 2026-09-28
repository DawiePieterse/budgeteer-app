<?php

use App\Services\BudgetPeriod;
use Carbon\CarbonImmutable;

it('runs calendar months when the period starts on the 1st', function () {
    $p = BudgetPeriod::containing(CarbonImmutable::parse('2026-07-15'), 1);

    expect($p->from->toDateString())->toBe('2026-07-01')
        ->and($p->to->toDateString())->toBe('2026-07-31')
        ->and($p->label())->toBe('July 2026');
});

it('runs from payday to the day before the next payday', function () {
    $before = BudgetPeriod::containing(CarbonImmutable::parse('2026-07-10'), 25);
    $after = BudgetPeriod::containing(CarbonImmutable::parse('2026-07-25'), 25);

    expect($before->from->toDateString())->toBe('2026-06-25')
        ->and($before->to->toDateString())->toBe('2026-07-24')
        ->and($after->from->toDateString())->toBe('2026-07-25')
        ->and($before->next()->from->toDateString())->toBe('2026-07-25')
        ->and($after->previous()->from->toDateString())->toBe('2026-06-25');
});
