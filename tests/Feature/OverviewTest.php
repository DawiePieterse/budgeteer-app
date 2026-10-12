<?php

use App\Http\Controllers\OverviewController;
use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

/** A household with a budget, signed in, and a way to add its transactions. */
function overviewHousehold(): array
{
    $user = member();
    test()->actingAs($user);
    Category::where('name', 'Groceries')->update(['budget_cents' => 1000000]);
    Category::where('name', 'Eating out')->update(['budget_cents' => 200000]);
    Category::where('name', 'Fuel and car')->update(['budget_cents' => 300000]);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $add = fn (string $on, string $line, int $rand, array $more = []) => Transaction::factory()->for($account)->create(
        ['posted_on' => $on, 'amount_cents' => $rand * 100, 'category_id' => Category::where('name', $line)->sole()->id] + $more);

    return [$user, $add];
}

it('shows the month\'s totals and how they compare with the month before', function () {
    [, $add] = overviewHousehold();
    $add('2026-06-03', 'Groceries', -4000);
    $add('2026-06-25', 'Consulting', 50000);
    $add('2026-07-03', 'Groceries', -3000);
    $add('2026-07-08', 'Eating out', -2500); // over its R2,000
    $add('2026-07-09', 'Medical', -500);     // spending without a budget
    $add('2026-07-25', 'Consulting', 50000);

    $this->get('/overview?in=2026-07-15')->assertOk()
        ->assertSee('1 Jul – 31 Jul 2026')
        ->assertSeeInOrder(['Money in', 'R50,000', 'Same as Jun'])
        ->assertSeeInOrder(['Spent', 'R6,000', '50% more than Jun'])  // R6,000 against R4,000
        ->assertSeeInOrder(['Left to spend', 'R9,500', 'of R15,000'])  // R15,000 budget less R5,500 on budget lines
        ->assertSeeInOrder(['In − spent', '+R44,000'])
        ->assertDontSee('days to go')
        ->assertDontSee('a day');
});

it('compares a month still running with last month up to the same day', function () {
    CarbonImmutable::setTestNow('2026-07-10 09:00');
    [, $add] = overviewHousehold();
    $add('2026-06-05', 'Groceries', -2000);
    $add('2026-06-20', 'Groceries', -9000); // later in June than today is in July: not compared
    $add('2026-07-04', 'Groceries', -1500);

    $this->get('/overview')->assertOk()
        ->assertSee('21 days to go')
        ->assertSeeInOrder(['Spent so far', 'R1,500', '25% less than Jun by now'])
        ->assertSee('R13,500')                 // left to spend
        ->assertSee('R614 a day');             // R13,500 over the 22 days left, today included, to the nearest rand
});

it('compares the budget with what is spent per group, and the rest as Other', function () {
    [, $add] = overviewHousehold();
    $food = CategoryGroup::create(['name' => 'Food']);
    Category::whereIn('name', ['Groceries', 'Eating out'])->update(['category_group_id' => $food->id]);
    $add('2026-07-03', 'Groceries', -3000);
    $add('2026-07-08', 'Eating out', -2500);
    $add('2026-07-09', 'Fuel and car', -1000);

    $this->get('/overview?in=2026-07-15')->assertOk()
        ->assertSeeInOrder(['Budget vs real', 'By group', 'Food', 'R5,500', 'of R12,000', 'Other', 'R1,000', 'of R3,000', 'All spending', 'R6,500', 'of R15,000'])
        ->assertDontSee('Make groups');
});

it('compares the biggest lines until there are groups', function () {
    [, $add] = overviewHousehold();
    $add('2026-07-03', 'Groceries', -3000);

    $this->get('/overview?in=2026-07-15')->assertOk()
        ->assertSeeInOrder(['Budget vs real', 'The biggest lines', 'Groceries', 'R3,000', 'of R10,000'])
        ->assertSee('Make groups')
        ->assertDontSee('Show a group'); // no group filter either
});

it('shows one group\'s lines, or those without a group', function () {
    [, $add] = overviewHousehold();
    $food = CategoryGroup::create(['name' => 'Food']);
    Category::where('name', 'Groceries')->update(['category_group_id' => $food->id]);
    $add('2026-07-03', 'Groceries', -3000);
    $add('2026-07-09', 'Fuel and car', -1000);

    $lines = fn (string $query) => collect(explode('<tbody>', $this->get('/overview?in=2026-07-15'.$query)->getContent())[1]);

    expect($lines('&group='.$food->id)->first())->toContain('Groceries')->not->toContain('Fuel and car')
        ->and($lines('&group=none')->first())->toContain('Fuel and car')->not->toContain('Groceries')
        ->and($lines('')->first())->toContain('Fuel and car')->toContain('Groceries');
});

it('says what happened to each line\'s recurring payments', function () {
    CarbonImmutable::setTestNow('2026-07-10 09:00');
    [$user, $add] = overviewHousehold();
    $line = fn (string $name) => Category::where('name', $name)->sole()->id;
    $rent = RecurringPayment::create(['name' => 'Rent', 'match_text' => 'RENT', 'category_id' => $line('Home and levies'), 'amount_cents' => 900000, 'frequency' => 'monthly', 'day' => 1]);
    RecurringPayment::create(['name' => 'Medical aid', 'match_text' => 'MED', 'category_id' => $line('Medical'), 'amount_cents' => 400000, 'frequency' => 'monthly', 'day' => 2]);
    RecurringPayment::create(['name' => 'Netflix', 'match_text' => 'NETFLIX', 'category_id' => $line('Subscriptions and internet'), 'amount_cents' => 19900, 'frequency' => 'monthly', 'day' => 28]);
    Category::whereIn('name', ['Home and levies', 'Medical', 'Subscriptions and internet'])->update(['budget_cents' => 1000000]);
    $add('2026-07-01', 'Home and levies', -9000, ['recurring_payment_id' => $rent->id]);

    $this->get('/overview')->assertOk()
        ->assertSeeInOrder(['Home and levies', 'Paid 1 Jul'])
        ->assertSeeInOrder(['Medical', 'Late'])
        ->assertSeeInOrder(['Subscriptions and internet', 'Due 28 Jul']);
});

it('works for a household with nothing yet', function () {
    $this->actingAs(member());

    $this->get('/overview?in=2026-07-15')->assertOk()
        ->assertSee('Set a budget')
        ->assertSee('Nothing here in July')
        ->assertSee('Nothing to compare with in Jun');
});

it('is in the sidebar, not in the phone tabs', function () {
    $this->actingAs(member());

    expect($this->get('/')->getContent())->toMatch('#<a href="'.preg_quote(route('overview'), '#').'"\s+class="nav-item wide-only"#');
});

it('works out the change from last month, and whether it is good', function () {
    expect(OverviewController::change(10400, 10000, moreIsGood: false))->toBe(['pct' => 4, 'tone' => 'bad'])
        ->and(OverviewController::change(9600, 10000, moreIsGood: false))->toBe(['pct' => -4, 'tone' => 'good'])
        ->and(OverviewController::change(10400, 10000, moreIsGood: true))->toBe(['pct' => 4, 'tone' => 'good'])
        ->and(OverviewController::change(10020, 10000, moreIsGood: true))->toBe(['pct' => 0, 'tone' => 'same'])
        ->and(OverviewController::change(500, 0, moreIsGood: true))->toBeNull();
});

it('ignores a group filter that is not one value', function () {
    overviewHousehold();

    $this->get('/overview?group[]=1')->assertOk();
});
