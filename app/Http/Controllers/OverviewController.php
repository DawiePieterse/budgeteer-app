<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RecurringPayment;
use App\Recurring\Occurrence;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
use App\Services\MonthSummary;
use App\Services\Trends;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The desktop Overview: a calm summary of the budget month, for sitting down at the computer. */
class OverviewController extends Controller
{
    /** How far back the trends reach: 6 or 12 budget months, or all (at most five years). */
    public const RANGES = ['6' => 6, '12' => 12, 'all' => null];

    /** The donut shows at most this many groups; smaller ones join the last slice. */
    private const DONUT_SLICES = 6;

    public function __invoke(Request $request, RecurringSchedule $schedule): View
    {
        $household = $request->user()->household;
        $today = CarbonImmutable::today();
        $date = $request->date('in') ? CarbonImmutable::parse($request->date('in')) : $today;
        $period = BudgetPeriod::containing($date, $household->period_start_day);
        $isCurrent = $today->between($period->from, $period->to);

        $summary = MonthSummary::for($household, $period);
        // Last month up to the same day, while this one is still running, so "so far" compares like with like.
        $previous = $period->previous();
        $lastMonth = MonthSummary::for($household, $isCurrent
            ? new BudgetPeriod($previous->from, $previous->from->addDays((int) $period->from->diffInDays($today))->min($previous->to))
            : $previous);

        $range = is_string($request->query('range')) && array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '12';
        $count = self::RANGES[$range];
        // At least eight months are read, for the sparklines and the biggest changes, whatever the range.
        $trends = Trends::for($household, $period, $count === null ? null : max($count, 8));
        $months = $trends->months;
        $current = $months[count($months) - 1];
        $before = $months[count($months) - 2] ?? null;
        $history = $trends->before(12);
        $changes = $trends->biggestChanges($isCurrent);
        $names = Category::query()->pluck('name', 'id');

        $groupId = is_string($request->query('group')) ? $request->query('group') : null;
        $lines = array_values(array_filter($summary->spending, fn (array $row) => match (true) {
            $groupId === null || $groupId === '' => true,
            $groupId === 'none' => $row['group'] === null,
            default => $row['group'] === (int) $groupId,
        }));

        return view('overview', [
            'period' => $period,
            'isCurrent' => $isCurrent,
            'daysLeft' => $isCurrent ? (int) $today->diffInDays($period->to) : null,
            'summary' => $summary,
            'lastMonth' => $lastMonth,
            'lastMonthName' => self::monthOf($previous)->format('M'),
            'monthName' => self::monthOf($period)->format('F'),
            'range' => $range,
            'chartMonths' => array_map(fn (array $m) => [
                'label' => self::monthOf($m['period'])->format('M'),
                'title' => self::monthOf($m['period'])->format('F Y'),
                'spent' => $m['spent'],
                'received' => $m['received'],
                'running' => $isCurrent && $m['period']->from->equalTo($period->from),
                'href' => route('transactions.index', ['month' => $m['period']->from->toDateString()]),
            ], $trends->last($count)),
            'sparks' => [
                'in' => array_column($history, 'received'),
                'out' => array_column($history, 'spent'),
                'net' => array_map(fn (array $m) => $m['received'] - $m['spent'], $history),
            ],
            'netAverage' => $history === [] ? null : (int) round(array_sum(array_map(fn (array $m) => $m['received'] - $m['spent'], $history)) / count($history)),
            'lineSparks' => collect($trends->before(6))->flatMap(fn (array $m) => array_keys($m['lines']))->unique()
                ->mapWithKeys(fn ($key) => [$key => array_map(fn (array $m) => $m['lines'][$key] ?? 0, $trends->before(6))])->all(),
            'pace' => [
                'now' => Trends::runningTotal($current, $isCurrent ? $today : null),
                'last' => $before !== null ? Trends::runningTotal($before) : [],
                'days' => (int) $period->from->diffInDays($period->to) + 1,
            ],
            'donut' => self::slices(array_values(array_filter($summary->groupTotals(), fn (array $g) => $g['cents'] > 0))),
            'changes' => array_map(fn (array $c) => $c + ['name' => $names->get($c['id'], '')], $changes['changes']),
            'changesMonth' => $changes['month'] !== null ? self::monthOf($changes['month'])->format('F') : null,
            'changesIn' => $changes['month']?->from->toDateString(),
            'groupTotals' => $summary->groupTotals(),
            'lines' => $lines,
            'groupId' => $groupId,
            'statuses' => $this->statuses($schedule, $period, $isCurrent),
            'inChange' => self::change($summary->received(), $lastMonth->received(), moreIsGood: true),
            'outChange' => self::change($summary->spent(), $lastMonth->spent(), moreIsGood: false),
        ]);
    }

    /** A budget month is called after the month most of its days are in: 25 Sep to 24 Oct is October. */
    private static function monthOf(BudgetPeriod $period): CarbonImmutable
    {
        return $period->from->day <= 15 ? $period->from : $period->to;
    }

    /**
     * The donut's slices: the groups, with any past the sixth together as one, so the ring stays readable.
     *
     * @param  list<array{id: int|null, name: string, colour: string|null, cents: int, budget: int}>  $groups
     * @return list<array{id: int|null, name: string, colour: string|null, cents: int, budget: int}>
     */
    private static function slices(array $groups): array
    {
        if (count($groups) <= self::DONUT_SLICES) {
            return $groups;
        }
        $rest = array_slice($groups, self::DONUT_SLICES - 1);

        return [...array_slice($groups, 0, self::DONUT_SLICES - 1), ['id' => null, 'name' => 'The rest', 'colour' => null,
            'cents' => (int) array_sum(array_column($rest, 'cents')), 'budget' => (int) array_sum(array_column($rest, 'budget'))]];
    }

    /**
     * How this month compares with last month, as a share: "4% less", "same". Null when last month had
     * nothing to compare with.
     *
     * @return array{pct: int, tone: string}|null
     */
    public static function change(int $now, int $then, bool $moreIsGood): ?array
    {
        if ($then <= 0) {
            return null;
        }
        $pct = (int) round(($now - $then) / $then * 100);

        return ['pct' => $pct, 'tone' => match (true) {
            $pct === 0 => 'same',
            ($pct > 0) === $moreIsGood => 'good',
            default => 'bad',
        }];
    }

    /**
     * What happened this month to each line's recurring payments, in a few words: "Paid 1 Oct", "Due 28 Oct",
     * "Late"; for a line with several, how many are paid.
     *
     * @return array<int, array{text: string, tone: string}>
     */
    private function statuses(RecurringSchedule $schedule, BudgetPeriod $period, bool $isCurrent): array
    {
        // In a month that is over, only what happened: a payment not seen then may not have existed yet.
        $occurrences = collect($schedule->occurrences(RecurringPayment::query()->where('active', true)->whereNotNull('category_id')->get(), $period))
            ->reject(fn (Occurrence $o) => $o->status === Occurrence::SKIPPED || (! $isCurrent && in_array($o->status, [Occurrence::DUE, Occurrence::LATE], true)));

        $statuses = [];
        foreach ($occurrences->groupBy(fn (Occurrence $o) => $o->payment->category_id) as $categoryId => $group) {
            $paid = $group->filter(fn (Occurrence $o) => in_array($o->status, [Occurrence::PAID, Occurrence::PAID_BY_HAND], true));
            $late = $group->where('status', Occurrence::LATE)->count();
            $changed = $group->where('status', Occurrence::CHANGED)->count();
            $only = $group->count() === 1 ? $group->first() : null;
            $statuses[(int) $categoryId] = match (true) {
                $late > 0 => ['text' => $only ? 'Late' : "{$late} late", 'tone' => 'out'],
                $changed > 0 => ['text' => $only ? 'Amount changed' : "{$changed} changed", 'tone' => 'warn'],
                $only !== null && $paid->isNotEmpty() => ['text' => 'Paid '.($only->transaction->posted_on ?? $only->dueOn)->format('j M'), 'tone' => 'in'],
                $only !== null => ['text' => 'Due '.$only->dueOn->format('j M'), 'tone' => 'muted'],
                $paid->count() === $group->count() => ['text' => 'All paid', 'tone' => 'in'],
                default => ['text' => $paid->count().' of '.$group->count().' paid', 'tone' => 'muted'],
            };
        }

        return $statuses;
    }
}
