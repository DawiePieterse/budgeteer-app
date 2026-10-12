<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Person;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetPeriod;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;

/** July 2026 for a household whose budget month starts on the 1st. */
function july2026(): BudgetPeriod
{
    return BudgetPeriod::containing(CarbonImmutable::parse('2026-07-15'), 1);
}

function summaryLine(User $user, string $name, ?int $budget = null): Category
{
    $category = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', $name)->sole();
    $category->update(['budget_cents' => $budget]);

    return $category;
}

it('adds up the month per budget line, with money in and the budget', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = summaryLine($user, 'Groceries', 1000000);
    $medical = summaryLine($user, 'Medical', 500000);
    $fees = summaryLine($user, 'Bank fees');
    $consulting = summaryLine($user, 'Consulting', 6000000);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -400000, 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-20', 'amount_cents' => -300000, 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-21', 'amount_cents' => 25000, 'category_id' => $groceries->id]); // a refund
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-10', 'amount_cents' => -9600, 'category_id' => $fees->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-25', 'amount_cents' => 5500000, 'category_id' => $consulting->id]);

    $summary = MonthSummary::for($user->household, july2026());

    expect($summary->spending)->toHaveCount(3)
        ->and($summary->spending[0])->toMatchArray(['id' => $groceries->id, 'name' => 'Groceries', 'cents' => 675000, 'budget' => 1000000])
        ->and($summary->spending[1])->toMatchArray(['id' => $medical->id, 'cents' => 0, 'budget' => 500000]) // a budget line with nothing spent yet
        ->and($summary->spending[2])->toMatchArray(['id' => $fees->id, 'cents' => 9600, 'budget' => null]) // not on the budget: last
        ->and($summary->income)->toBe([['name' => 'Consulting', 'cents' => 5500000]])
        ->and($summary->budgeted)->toBe(1500000) // expense lines only, not expected income
        ->and($summary->spent())->toBe(684600)
        ->and($summary->spentOnLines())->toBe(675000)
        ->and($summary->received())->toBe(5500000)
        ->and(array_column($summary->budgetLines(), 'name'))->toBe(['Groceries', 'Medical']);
});

it('puts the budget line most used of its budget first', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = summaryLine($user, 'Groceries', 1000000);
    $eatingOut = summaryLine($user, 'Eating out', 200000);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -500000, 'category_id' => $groceries->id]); // 50%
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -180000, 'category_id' => $eatingOut->id]); // 90%

    expect(array_column(MonthSummary::for($user->household, july2026())->spending, 'name'))->toBe(['Eating out', 'Groceries']);
});

it('leaves out transfers, purchases for others, projects and other months', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = summaryLine($user, 'Groceries', 1000000);
    $person = Person::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $project = Project::create(['household_id' => $user->household_id, 'name' => 'Car rebuild']);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -100000, 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-04', 'amount_cents' => -800000, 'is_transfer' => true]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-05', 'amount_cents' => -200000, 'person_id' => $person->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-06', 'amount_cents' => -300000, 'project_id' => $project->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-06-30', 'amount_cents' => -400000, 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-08-01', 'amount_cents' => -500000, 'category_id' => $groceries->id]);

    $summary = MonthSummary::for($user->household, july2026());

    expect($summary->spent())->toBe(100000)->and($summary->received())->toBe(0);
});

it('keeps uncategorised money in and out apart', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -70000]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-04', 'amount_cents' => 120000]);

    $summary = MonthSummary::for($user->household, july2026());

    expect($summary->spending)->toHaveCount(1)
        ->and($summary->spending[0])->toMatchArray(['id' => null, 'name' => 'Not categorised yet', 'cents' => 70000, 'budget' => null])
        ->and($summary->income)->toBe([['name' => 'Not categorised yet', 'cents' => 120000]]);
});

it('reads only the given household, also with nobody signed in', function () {
    $ours = member();
    $theirs = member();
    $groceries = summaryLine($ours, 'Groceries', 1000000);
    summaryLine($theirs, 'Groceries', 9900000);
    Transaction::factory()->for(Account::factory()->create(['household_id' => $ours->household_id]))
        ->create(['posted_on' => '2026-07-03', 'amount_cents' => -100000, 'category_id' => $groceries->id]);
    Transaction::factory()->for(Account::factory()->create(['household_id' => $theirs->household_id]))
        ->create(['posted_on' => '2026-07-03', 'amount_cents' => -900000]);

    $summary = MonthSummary::for($ours->household, july2026());

    expect($summary->spent())->toBe(100000)->and($summary->budgeted)->toBe(1000000);
});

it('adds up spending and budget per group, with lines without a group as Other', function () {
    $user = member();
    $this->actingAs($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $food = CategoryGroup::create(['name' => 'Food']);
    $car = CategoryGroup::create(['name' => 'Car']); // nothing in it yet
    $groceries = summaryLine($user, 'Groceries', 1000000);
    $eatingOut = summaryLine($user, 'Eating out', 200000);
    $medical = summaryLine($user, 'Medical', 150000);
    Category::whereIn('id', [$groceries->id, $eatingOut->id])->update(['category_group_id' => $food->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -300000, 'category_id' => $groceries->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-04', 'amount_cents' => -50000, 'category_id' => $eatingOut->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-05', 'amount_cents' => -20000, 'category_id' => $medical->id]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-07-06', 'amount_cents' => -7000]); // not categorised

    expect(MonthSummary::for($user->household, july2026())->groupTotals())->toBe([
        ['id' => $food->id, 'name' => 'Food', 'colour' => 'indigo', 'cents' => 350000, 'budget' => 1200000],
        ['id' => $car->id, 'name' => 'Car', 'colour' => 'orange', 'cents' => 0, 'budget' => 0],
        ['id' => null, 'name' => 'Other', 'colour' => null, 'cents' => 27000, 'budget' => 150000],
    ]);
});

it('gives the biggest lines and everything else when there are no groups', function () {
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    foreach (['Groceries' => 6, 'Eating out' => 5, 'Fuel and car' => 4, 'Medical' => 3, 'Insurance' => 2, 'Giving' => 1, 'Cash' => 1] as $name => $rand) {
        Transaction::factory()->for($account)->create(['posted_on' => '2026-07-03', 'amount_cents' => -$rand * 100, 'category_id' => summaryLine($user, $name)->id]);
    }

    $totals = MonthSummary::for($user->household, july2026())->groupTotals();

    expect(array_column($totals, 'name'))->toBe(['Groceries', 'Eating out', 'Fuel and car', 'Medical', 'Insurance', 'Everything else'])
        ->and($totals[5]['cents'])->toBe(200);
});
