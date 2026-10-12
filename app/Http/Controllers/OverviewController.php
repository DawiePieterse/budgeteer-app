<?php

namespace App\Http\Controllers;

use App\Models\RecurringPayment;
use App\Recurring\Occurrence;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
use App\Services\MonthSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The desktop Overview: a calm summary of the budget month, for sitting down at the computer. */
class OverviewController extends Controller
{
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
            // A budget month is called after the month most of its days are in: 25 Sep to 24 Oct is October.
            'lastMonthName' => ($previous->from->day <= 15 ? $previous->from : $previous->to)->format('M'),
            'monthName' => ($period->from->day <= 15 ? $period->from : $period->to)->format('F'),
            'groupTotals' => $summary->groupTotals(),
            'lines' => $lines,
            'groupId' => $groupId,
            'statuses' => $this->statuses($schedule, $period),
            'inChange' => self::change($summary->received(), $lastMonth->received(), moreIsGood: true),
            'outChange' => self::change($summary->spent(), $lastMonth->spent(), moreIsGood: false),
        ]);
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
    private function statuses(RecurringSchedule $schedule, BudgetPeriod $period): array
    {
        $occurrences = collect($schedule->occurrences(RecurringPayment::query()->where('active', true)->whereNotNull('category_id')->get(), $period))
            ->reject(fn (Occurrence $o) => $o->status === Occurrence::SKIPPED);

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
