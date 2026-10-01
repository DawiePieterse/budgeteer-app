<?php

namespace App\Http\Controllers;

use App\Enums\CategoryKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Project;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Notify\NoticeFinder;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
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

        $rows = Transaction::query()
            ->where('is_transfer', false)
            ->whereNull('person_id')
            ->whereNull('project_id')
            ->whereBetween('posted_on', [$period->from, $period->to])
            ->get(['category_id', 'amount_cents']);

        $categories = Category::query()->get()->keyBy('id');
        $spending = [];
        $income = [];
        // Not yet categorised money in and money out are kept apart, so neither hides the other.
        foreach ($rows->groupBy(fn (Transaction $t) => $t->category_id ?? ($t->amount_cents > 0 ? 'in' : 'out')) as $categoryId => $group) {
            $sum = (int) $group->sum('amount_cents');
            $category = $categories->get($categoryId);
            if ($category?->kind === CategoryKind::Income || $categoryId === 'in') {
                $income[] = ['name' => $category->name ?? 'Not categorised yet', 'cents' => $sum];
            } else {
                $spending[(string) $categoryId] = ['id' => $category?->id, 'name' => $category->name ?? 'Not categorised yet', 'cents' => -$sum, 'budget' => $category?->budget_cents, 'icon' => $category?->iconName() ?? 'tag', 'sort' => $category?->sort ?? PHP_INT_MAX];
            }
        }
        // Every budget line shows, also those with nothing spent yet.
        foreach ($categories as $category) {
            if ($category->kind === CategoryKind::Expense && $category->budget_cents !== null && ! isset($spending[(string) $category->id])) {
                $spending[(string) $category->id] = ['id' => $category->id, 'name' => $category->name, 'cents' => 0, 'budget' => $category->budget_cents, 'icon' => $category->iconName(), 'sort' => $category->sort];
            }
        }
        $spending = array_values($spending);
        // Budget lines first, the most used of their budget on top; then unbudgeted spending, biggest first.
        usort($spending, fn ($a, $b) => [($a['budget'] ?? null) === null, -self::share($a), -$a['cents']] <=> [($b['budget'] ?? null) === null, -self::share($b), -$b['cents']]);
        usort($income, fn ($a, $b) => $b['cents'] <=> $a['cents']);

        // Circles or list: each phone keeps its own choice.
        $view = in_array($request->query('view'), [self::CIRCLES, self::LIST], true) ? $request->query('view') : $request->cookie('budget_view');
        $view = $view === self::LIST ? self::LIST : self::CIRCLES;
        if ($request->query('view') === $view) {
            Cookie::queue('budget_view', $view, 60 * 24 * 365);
        }

        $budgetLines = array_filter($spending, fn ($row) => ($row['budget'] ?? null) !== null);

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
            'spentOnLines' => (int) array_sum(array_column($budgetLines, 'cents')),
            'spending' => $spending,
            'income' => $income,
            'spent' => array_sum(array_column($spending, 'cents')),
            'budgeted' => (int) $categories->where('kind', CategoryKind::Expense)->sum('budget_cents'),
            'received' => array_sum(array_column($income, 'cents')),
            'toCategorise' => Transaction::query()->toReview()->count(),
            'statementsDue' => $notices->statementsDue($household, $today),
            'projects' => Project::query()->orderBy('name')->get(),
            'recurring' => $schedule->occurrences(RecurringPayment::query()->where('active', true)->get(), $period),
            // Only people with something open: once someone is all square they drop off until the next purchase.
            'owedToUs' => Person::query()->orderBy('name')->get()->map(fn (Person $p) => ['person' => $p, 'cents' => $balances->owed($p)])->filter(fn ($row) => $row['cents'] !== 0)->values(),
            'accounts' => Account::query()->orderBy('bank')->get(),
        ]);
    }

    /** @param array{cents: int, budget?: int|null} $row */
    private static function share(array $row): float
    {
        return ($row['budget'] ?? 0) > 0 ? $row['cents'] / $row['budget'] : 0.0;
    }
}
