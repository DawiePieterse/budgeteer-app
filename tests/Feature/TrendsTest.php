<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Transaction;
use App\Services\BudgetPeriod;
use App\Services\Trends;
use Carbon\CarbonImmutable;

function trendsHousehold(int $startDay = 1): array
{
    $user = member(['period_start_day' => $startDay]);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $id = fn (string $name) => Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', $name)->sole()->id;
    $add = fn (string $on, ?string $line, int $rand, array $more = []) => Transaction::factory()->for($account)->create(
        ['posted_on' => $on, 'amount_cents' => $rand * 100, 'category_id' => $line !== null ? $id($line) : null] + $more);

    return [$user, $add, $id];
}

it('sorts transactions into budget months that start on the household\'s day', function () {
    [$user, $add, $id] = trendsHousehold(25);
    $add('2026-08-24', 'Groceries', -100);   // the month 25 Jul to 24 Aug
    $add('2026-08-25', 'Groceries', -200);   // 25 Aug to 24 Sep
    $add('2026-09-24', 'Groceries', -300);
    $add('2026-09-25', 'Consulting', 5000);  // 25 Sep to 24 Oct
    $add('2026-10-01', null, 70);            // uncategorised money in
    $add('2026-10-02', null, -40);           // uncategorised money out
    $add('2026-10-03', 'Groceries', -25, ['is_transfer' => true]);
    $add('2026-10-04', 'Groceries', -25, ['person_id' => Person::create(['household_id' => $user->household_id, 'name' => 'Sam'])->id]);

    $last = BudgetPeriod::containing(CarbonImmutable::parse('2026-10-10'), 25);
    $months = Trends::for($user->household, $last, 12)->months;

    expect($months)->toHaveCount(3) // from the first month with a transaction
        ->and(array_map(fn ($m) => $m['period']->from->toDateString(), $months))->toBe(['2026-07-25', '2026-08-25', '2026-09-25'])
        ->and(array_column($months, 'spent'))->toBe([10000, 50000, 4000])
        ->and(array_column($months, 'received'))->toBe([0, 0, 507000])
        ->and($months[1]['lines'])->toBe([$id('Groceries') => 50000])
        ->and($months[2]['lines'])->toBe(['out' => 4000])
        ->and($months[1]['days'])->toBe(['2026-08-25' => 20000, '2026-09-24' => 30000]);
});

it('keeps only as many months as asked for', function () {
    [$user, $add] = trendsHousehold();
    foreach (range(1, 9) as $m) {
        $add(sprintf('2026-%02d-10', $m), 'Groceries', -100 * $m);
    }
    $last = BudgetPeriod::containing(CarbonImmutable::parse('2026-09-15'), 1);
    $trends = Trends::for($user->household, $last, 6);

    expect($trends->months)->toHaveCount(6)
        ->and(array_column($trends->last(3), 'spent'))->toBe([70000, 80000, 90000])
        ->and(array_column($trends->before(2), 'spent'))->toBe([70000, 80000])
        ->and(Trends::for($user->household, $last, null)->months)->toHaveCount(9);
});

it('adds up a month day by day, up to a given day', function () {
    [$user, $add] = trendsHousehold();
    $add('2026-07-01', 'Groceries', -100);
    $add('2026-07-03', 'Groceries', -50);
    $month = Trends::for($user->household, BudgetPeriod::containing(CarbonImmutable::parse('2026-07-15'), 1), 1)->months[0];

    expect(Trends::runningTotal($month, CarbonImmutable::parse('2026-07-04')))->toBe([10000, 10000, 15000, 15000])
        ->and(Trends::runningTotal($month))->toHaveCount(31);
});

it('finds the lines that changed most in the last full month', function () {
    [$user, $add, $id] = trendsHousehold();
    foreach (range(1, 6) as $m) {
        $add(sprintf('2026-%02d-05', $m), 'Eating out', -1000);
        $add(sprintf('2026-%02d-05', $m), 'Groceries', -8000);
        $add(sprintf('2026-%02d-05', $m), 'Clothing and shopping', -1000);
        $add(sprintf('2026-%02d-05', $m), 'Giving', -100); // too small to matter
    }
    $add('2026-07-05', 'Eating out', -2500);             // +150%
    $add('2026-07-05', 'Groceries', -8100);              // +1%, R100: too small a change
    $add('2026-07-05', 'Clothing and shopping', -400);   // -60%
    $add('2026-07-05', 'Giving', -250);                  // under R300 a month: left out
    $add('2026-08-05', 'Groceries', -1);                 // August, still running

    $trends = Trends::for($user->household, BudgetPeriod::containing(CarbonImmutable::parse('2026-08-10'), 1), 12);
    $result = $trends->biggestChanges(lastIsRunning: true);

    expect($result['month']->from->toDateString())->toBe('2026-07-01')
        ->and($result['changes'])->toBe([
            ['id' => $id('Eating out'), 'cents' => 250000, 'average' => 100000, 'pct' => 150],
            ['id' => $id('Clothing and shopping'), 'cents' => 40000, 'average' => 100000, 'pct' => -60],
        ]);
});

it('needs three months before it to find changes', function () {
    [$user, $add] = trendsHousehold();
    $add('2026-06-05', 'Eating out', -1000);
    $add('2026-07-05', 'Eating out', -3000);

    expect(Trends::for($user->household, BudgetPeriod::containing(CarbonImmutable::parse('2026-07-10'), 1), 12)->biggestChanges(false)['changes'])->toBe([]);
});
