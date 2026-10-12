<?php

namespace App\Services;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * Budget months side by side, for the Overview's trends: money in, spending, spending per line and per
 * day. Read with one query over the whole range and sorted into budget months here, since months start
 * on the household's own day. The same rules as MonthSummary: transfers between own accounts, purchases
 * charged to people and special projects are left out.
 *
 * @phpstan-type Month array{period: BudgetPeriod, received: int, spent: int, lines: array<int|string, int>, days: array<string, int>}
 */
final class Trends
{
    /** @param list<Month> $months oldest first, the last one the month asked for */
    private function __construct(public readonly array $months) {}

    /**
     * The budget months up to and including $last: $count of them, or all since the first transaction
     * (at most five years). Months before the first transaction are left out.
     */
    public static function for(Household $household, BudgetPeriod $last, ?int $count): self
    {
        $first = Transaction::withoutGlobalScopes()->where('household_id', $household->id)->min('posted_on');
        $stop = $first === null ? $last->from : BudgetPeriod::containing(CarbonImmutable::parse($first), $last->from->day)->from;

        $periods = [$last];
        while (count($periods) < ($count ?? 60) && $periods[0]->from->greaterThan($stop)) {
            array_unshift($periods, $periods[0]->previous());
        }

        $kinds = Category::withoutGlobalScopes()->where('household_id', $household->id)->pluck('kind', 'id');
        $rows = Transaction::withoutGlobalScopes()->where('household_id', $household->id)
            ->where('is_transfer', false)->whereNull('person_id')->whereNull('project_id')
            ->whereBetween('posted_on', [$periods[0]->from->toDateString(), $last->to->toDateString()])
            // Uncategorised money in and out are told apart by sign, as on Home.
            ->selectRaw('posted_on, category_id, amount_cents > 0 as is_in, sum(amount_cents) as cents')
            ->groupBy('posted_on', 'category_id', 'is_in')
            ->get();

        $months = array_map(fn (BudgetPeriod $p) => ['period' => $p, 'received' => 0, 'spent' => 0, 'lines' => [], 'days' => []], $periods);
        $i = 0;
        foreach ($rows->sortBy('posted_on') as $row) {
            $day = CarbonImmutable::parse($row->posted_on);
            while ($day->greaterThan($months[$i]['period']->to)) {
                $i++;
            }
            $cents = (int) $row->getAttribute('cents');
            $kind = $row->category_id !== null ? $kinds->get($row->category_id) : null;
            if ($kind === CategoryKind::Income || ($row->category_id === null && (bool) $row->getAttribute('is_in'))) {
                $months[$i]['received'] += $cents;

                continue;
            }
            $key = $row->category_id ?? 'out';
            $months[$i]['spent'] -= $cents;
            $months[$i]['lines'][$key] = ($months[$i]['lines'][$key] ?? 0) - $cents;
            $months[$i]['days'][$day->toDateString()] = ($months[$i]['days'][$day->toDateString()] ?? 0) - $cents;
        }

        return new self($months);
    }

    /**
     * The last $count months (or all), oldest first.
     *
     * @return list<Month>
     */
    public function last(?int $count = null): array
    {
        return $count === null ? $this->months : array_slice($this->months, -$count);
    }

    /**
     * The months before the last one: the history the current month is compared with.
     *
     * @return list<Month>
     */
    public function before(int $count): array
    {
        return array_slice($this->months, max(0, count($this->months) - 1 - $count), min($count, count($this->months) - 1));
    }

    /**
     * Spent so far on each day of a month, as a running total from its first day; up to $until when given.
     *
     * @param  Month  $month
     * @return list<int>
     */
    public static function runningTotal(array $month, ?CarbonImmutable $until = null): array
    {
        $totals = [];
        $sum = 0;
        $to = $until !== null && $until->lessThan($month['period']->to) ? $until : $month['period']->to;
        for ($day = $month['period']->from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $sum += $month['days'][$day->toDateString()] ?? 0;
            $totals[] = $sum;
        }

        return $totals;
    }

    /**
     * Lines that changed most in the last full month against the average of the months before it: the
     * biggest rises and the biggest falls. With $lastIsRunning the last month is still going, so the one
     * before it is compared. Small lines and small changes are left out, and so is a history of fewer than
     * three months.
     *
     * @return array{month: BudgetPeriod|null, changes: list<array{id: int, cents: int, average: int, pct: int}>}
     */
    public function biggestChanges(bool $lastIsRunning, int $history = 6, int $minimum = 30000, int $minimumChange = 20000, int $each = 2): array
    {
        $months = $lastIsRunning ? array_slice($this->months, 0, -1) : $this->months;
        $last = $months === [] ? null : array_pop($months);
        $before = array_slice($months, -$history);
        if ($last === null || count($before) < 3) {
            return ['month' => $last['period'] ?? null, 'changes' => []];
        }
        $ids = collect([$last, ...$before])->flatMap(fn (array $m) => array_keys($m['lines']))->unique()->filter(fn ($id) => is_int($id));

        $changes = [];
        foreach ($ids as $id) {
            $now = $last['lines'][$id] ?? 0;
            $average = (int) round(array_sum(array_map(fn (array $m) => $m['lines'][$id] ?? 0, $before)) / count($before));
            if (max($now, $average) < $minimum || abs($now - $average) < $minimumChange || $average <= 0) {
                continue;
            }
            $changes[] = ['id' => $id, 'cents' => $now, 'average' => $average, 'pct' => (int) round(($now - $average) / $average * 100)];
        }
        usort($changes, fn ($a, $b) => $b['pct'] <=> $a['pct']);
        $rises = array_slice(array_values(array_filter($changes, fn ($c) => $c['pct'] > 0)), 0, $each);
        $falls = array_slice(array_reverse(array_values(array_filter($changes, fn ($c) => $c['pct'] < 0))), 0, $each);

        return ['month' => $last['period'], 'changes' => [...$rises, ...$falls]];
    }
}
