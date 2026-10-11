<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Person;
use App\Models\Project;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Notify\NoticeFinder;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
use App\Services\MonthSummary;
use App\Services\PersonBalance;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class HomeController extends Controller
{
    public const CIRCLES = 'circles';

    public const LIST = 'list';

    public function __invoke(Request $request, PersonBalance $balances, RecurringSchedule $schedule, NoticeFinder $notices): View
    {
        $household = $request->user()->household;
        $today = CarbonImmutable::today();
        $date = $request->date('in') ? CarbonImmutable::parse($request->date('in')) : $today;
        $period = BudgetPeriod::containing($date, $household->period_start_day);

        $summary = MonthSummary::for($household, $period);

        // Circles or list: each phone keeps its own choice.
        $view = in_array($request->query('view'), [self::CIRCLES, self::LIST], true) ? $request->query('view') : $request->cookie('budget_view');
        $view = $view === self::LIST ? self::LIST : self::CIRCLES;
        if ($request->query('view') === $view) {
            Cookie::queue('budget_view', $view, 60 * 24 * 365);
        }

        return view('home', [
            'budgetView' => $view,
            'period' => $period,
            // Which month this is, said the way people say it, and the days still to go in this one.
            'when' => match (true) {
                $today->between($period->from, $period->to) => 'This month',
                $today->between($period->next()->from, $period->next()->to) => 'Last month',
                $today->between($period->previous()->from, $period->previous()->to) => 'Next month',
                default => $period->label(),
            },
            'daysLeft' => $today->between($period->from, $period->to) ? (int) $today->diffInDays($period->to) : null,
            'spentOnLines' => $summary->spentOnLines(),
            'spending' => $summary->spending,
            'income' => $summary->income,
            'spent' => $summary->spent(),
            'budgeted' => $summary->budgeted,
            'received' => $summary->received(),
            'toCategorise' => Transaction::query()->toReview()->count(),
            'statementsDue' => $notices->statementsDue($household, $today),
            'projects' => Project::query()->orderBy('name')->get(),
            'recurring' => $schedule->occurrences(RecurringPayment::query()->where('active', true)->get(), $period),
            // Only people with something open: once someone is all square they drop off until the next purchase.
            'owedToUs' => Person::query()->orderBy('name')->get()->map(fn (Person $p) => ['person' => $p, 'cents' => $balances->owed($p)])->filter(fn ($row) => $row['cents'] !== 0)->values(),
            'accounts' => Account::query()->orderBy('bank')->get(),
        ]);
    }
}
