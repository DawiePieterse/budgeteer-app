<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Household;
use App\Models\Transaction;

/**
 * What came in and went out in one budget month, per category: the numbers Home and the Overview show.
 *
 * Money moved between our own accounts, purchases charged to someone who pays us back and special
 * projects' payments are left out; they are not the household's own spending.
 *
 * @phpstan-type SpendingRow array{id: int|null, name: string, cents: int, budget: int|null, icon: string, sort: int, group: int|null}
 * @phpstan-type GroupTotal array{id: int|null, name: string, colour: string|null, cents: int, budget: int}
 * @phpstan-type IncomeRow array{name: string, cents: int}
 */
final class MonthSummary
{
    /**
     * @param  list<SpendingRow>  $spending  budget lines first, the most used of their budget on top; then unbudgeted spending, biggest first
     * @param  list<IncomeRow>  $income  biggest first
     * @param  int  $budgeted  the expense budgets added up
     * @param  list<CategoryGroup>  $groups  the household's groups, in their order
     */
    private function __construct(
        public readonly BudgetPeriod $period,
        public readonly array $spending,
        public readonly array $income,
        public readonly int $budgeted,
        public readonly array $groups = [],
    ) {}

    /** Works without anyone signed in (for scheduled jobs too): only the given household's rows are read. */
    public static function for(Household $household, BudgetPeriod $period): self
    {
        $rows = Transaction::withoutGlobalScopes()->where('household_id', $household->id)
            ->where('is_transfer', false)
            ->whereNull('person_id')
            ->whereNull('project_id')
            ->whereBetween('posted_on', [$period->from, $period->to])
            ->get(['category_id', 'amount_cents']);

        $categories = Category::withoutGlobalScopes()->where('household_id', $household->id)->get()->keyBy('id');
        $spending = [];
        $income = [];
        // Not yet categorised money in and money out are kept apart, so neither hides the other.
        foreach ($rows->groupBy(fn (Transaction $t) => $t->category_id ?? ($t->amount_cents > 0 ? 'in' : 'out')) as $categoryId => $group) {
            $sum = (int) $group->sum('amount_cents');
            $category = $categories->get($categoryId);
            if ($category?->kind === CategoryKind::Income || $categoryId === 'in') {
                $income[] = ['name' => $category->name ?? 'Not categorised yet', 'cents' => $sum];
            } else {
                $spending[(string) $categoryId] = ['id' => $category?->id, 'name' => $category->name ?? 'Not categorised yet', 'cents' => -$sum, 'budget' => $category?->budget_cents, 'icon' => $category?->iconName() ?? 'tag', 'sort' => $category->sort ?? PHP_INT_MAX, 'group' => $category?->category_group_id];
            }
        }
        // Every budget line shows, also those with nothing spent yet.
        foreach ($categories as $category) {
            if ($category->kind === CategoryKind::Expense && $category->budget_cents !== null && ! isset($spending[(string) $category->id])) {
                $spending[(string) $category->id] = ['id' => $category->id, 'name' => $category->name, 'cents' => 0, 'budget' => $category->budget_cents, 'icon' => $category->iconName(), 'sort' => $category->sort, 'group' => $category->category_group_id];
            }
        }
        $spending = array_values($spending);
        usort($spending, fn ($a, $b) => [$a['budget'] === null, -self::share($a), -$a['cents']] <=> [$b['budget'] === null, -self::share($b), -$b['cents']]);
        usort($income, fn ($a, $b) => $b['cents'] <=> $a['cents']);

        $groups = CategoryGroup::withoutGlobalScopes()->where('household_id', $household->id)->orderBy('sort')->orderBy('name')->get()->all();

        return new self($period, $spending, $income, (int) $categories->where('kind', CategoryKind::Expense)->sum('budget_cents'), $groups);
    }

    /** @return list<SpendingRow> the lines with a budget */
    public function budgetLines(): array
    {
        return array_values(array_filter($this->spending, fn (array $row) => $row['budget'] !== null));
    }

    /** All the household's own spending, on a budget line or not. */
    public function spent(): int
    {
        return (int) array_sum(array_column($this->spending, 'cents'));
    }

    /** Spending on the budget lines only: what the budget is measured against. */
    public function spentOnLines(): int
    {
        return (int) array_sum(array_column($this->budgetLines(), 'cents'));
    }

    public function received(): int
    {
        return (int) array_sum(array_column($this->income, 'cents'));
    }

    /**
     * Spending and budget per group, in the groups' order; lines without a group (and spending not yet
     * categorised) together as "Other" at the end. With no groups yet, the five biggest lines and
     * "Everything else", so there is something to compare from the first day.
     *
     * @return list<GroupTotal>
     */
    public function groupTotals(): array
    {
        $total = fn (array $rows) => ['cents' => (int) array_sum(array_column($rows, 'cents')), 'budget' => (int) array_sum(array_column($rows, 'budget'))];

        if ($this->groups === []) {
            $rows = $this->spending;
            usort($rows, fn ($a, $b) => [$b['cents'], $b['budget'] ?? 0] <=> [$a['cents'], $a['budget'] ?? 0]);
            $top = array_map(fn (array $row) => ['id' => null, 'name' => $row['name'], 'colour' => null, 'cents' => $row['cents'], 'budget' => (int) $row['budget']], array_slice($rows, 0, 5));
            $rest = array_slice($rows, 5);

            return $rest === [] ? $top : [...$top, ['id' => null, 'name' => 'Everything else', 'colour' => null] + $total($rest)];
        }

        $totals = [];
        foreach ($this->groups as $group) {
            $totals[] = ['id' => $group->id, 'name' => $group->name, 'colour' => $group->colour] + $total(array_filter($this->spending, fn (array $row) => $row['group'] === $group->id));
        }
        $other = $total(array_filter($this->spending, fn (array $row) => $row['group'] === null));
        if ($other['cents'] !== 0 || $other['budget'] !== 0) {
            $totals[] = ['id' => null, 'name' => 'Other', 'colour' => null] + $other;
        }

        return $totals;
    }

    /** @param SpendingRow $row */
    private static function share(array $row): float
    {
        return ($row['budget'] ?? 0) > 0 ? $row['cents'] / $row['budget'] : 0.0;
    }
}
