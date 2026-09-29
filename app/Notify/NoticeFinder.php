<?php

namespace App\Notify;

use App\Enums\CategoryKind;
use App\Models\Account;
use App\Models\Category;
use App\Models\GmailConnection;
use App\Models\Household;
use App\Models\RecurringPayment;
use App\Models\StatementImport;
use App\Models\Transaction;
use App\Recurring\Occurrence;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/** Looks at a household's money as it is now and lists everything that could be worth a notification. */
class NoticeFinder
{
    /** A budget line gets a warning at this share of its budget, and another when it goes over. */
    public const WARN_AT = 0.8;

    /** Late payments older than this are no longer news. */
    private const LATE_DAYS = 45;

    /** A changed amount is only news while the payment is this recent. */
    private const CHANGED_DAYS = 10;

    /** The month-end summary goes from this hour on the first day of the new budget month… */
    private const SUMMARY_HOUR = 8;

    /** …and is still sent this many days into it, if the server missed the first. */
    private const SUMMARY_DAYS = 3;

    /** A bank's statement is usually out this many days after it ends. */
    private const STATEMENT_OUT_AFTER_DAYS = 3;

    /** A statement still not uploaded gets a second reminder this much later. */
    private const STATEMENT_AGAIN_AFTER_DAYS = 7;

    public function __construct(private RecurringSchedule $schedule) {}

    /** @return list<Notice> */
    public function find(Household $household, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $today = $now->startOfDay();
        $period = BudgetPeriod::containing($today, $household->period_start_day);

        return [
            ...$this->recurring($household, $period, $today),
            ...$this->budget($household, $period, $today),
            ...$this->gmail($household),
            ...$this->summary($household, $period, $now),
            ...$this->statements($household, $today),
        ];
    }

    /** @return list<Notice> */
    private function recurring(Household $household, BudgetPeriod $period, CarbonImmutable $today): array
    {
        $payments = RecurringPayment::withoutGlobalScopes()->where('household_id', $household->id)->where('active', true)->get();
        if ($payments->isEmpty()) {
            return [];
        }
        $url = route('recurring.index', absolute: false);

        $notices = [];
        foreach ([$period->previous(), $period] as $p) {
            foreach ($this->schedule->occurrences($payments, $p, $today) as $o) {
                $name = $o->payment->name;
                $due = $o->dueOn->format('j M');
                if ($o->status === Occurrence::LATE && $o->dueOn->greaterThanOrEqualTo($today->subDays(self::LATE_DAYS))) {
                    $notices[] = new Notice(Notice::RECURRING, "late:{$o->payment->id}:{$o->dueOn->toDateString()}",
                        "Late: {$name}",
                        ($o->payment->amount_varies ? 'It' : money($o->payment->amount_cents)).' was due on '.$due.' and has not gone off yet.',
                        $url);
                } elseif ($o->status === Occurrence::CHANGED && $o->transaction !== null
                    && $o->transaction->posted_on->greaterThanOrEqualTo($today->subDays(self::CHANGED_DAYS))) {
                    $notices[] = new Notice(Notice::RECURRING, "changed:{$o->payment->id}:{$o->dueOn->toDateString()}",
                        "Amount changed: {$name}",
                        money(abs($o->transaction->amount_cents)).' went off on '.$o->transaction->posted_on->format('j M').'; expected '.money($o->payment->amount_cents).'.',
                        $url);
                }
            }
        }

        return $notices;
    }

    /** @return list<Notice> */
    private function budget(Household $household, BudgetPeriod $period, CarbonImmutable $today): array
    {
        $lines = Category::withoutGlobalScopes()->where('household_id', $household->id)
            ->where('kind', CategoryKind::Expense)->where('budget_cents', '>', 0)->orderBy('sort')->get();
        if ($lines->isEmpty()) {
            return [];
        }
        $spent = $this->spentByLine($household, $lines, $period);

        $from = $period->from->toDateString();
        $daysLeft = (int) $today->diffInDays($period->to) + 1;
        $toGo = $daysLeft === 1 ? 'the last day' : $daysLeft.' days to go';

        $notices = [];
        foreach ($lines as $line) {
            $cents = (int) ($spent[$line->id] ?? 0);
            $budget = (int) $line->budget_cents;
            $pct = (int) floor($cents * 100 / $budget);
            $url = route('transactions.index', ['category' => $line->id, 'month' => $from], absolute: false);
            if ($cents > $budget) {
                $notices[] = new Notice(Notice::BUDGET, "over:{$line->id}:{$from}",
                    "{$line->name} is over budget",
                    money($cents).' of '.money($budget).' spent: '.money($cents - $budget)." over ({$pct}%), {$toGo}.",
                    $url, ["near:{$line->id}:{$from}"]);
            } elseif ($cents >= $budget * self::WARN_AT) {
                $notices[] = new Notice(Notice::BUDGET, "near:{$line->id}:{$from}",
                    "{$line->name} at {$pct}%",
                    money($cents).' of '.money($budget).' spent: '.money($budget - $cents)." left, {$toGo}.",
                    $url);
            }
        }

        $total = (int) $spent->sum();
        $budgeted = (int) $lines->sum('budget_cents');
        if ($total > $budgeted) {
            $notices[] = new Notice(Notice::BUDGET, "over:total:{$from}",
                'The whole budget is over',
                money($total).' of '.money($budgeted).' spent on budget lines: '.money($total - $budgeted)." over, {$toGo}.",
                route('home', absolute: false));
        }

        return $notices;
    }

    /**
     * On the first days of a new budget month (from 08:00), how the month before went.
     *
     * @return list<Notice>
     */
    private function summary(Household $household, BudgetPeriod $period, CarbonImmutable $now): array
    {
        if ($now->hour < self::SUMMARY_HOUR || $now->startOfDay()->greaterThan($period->from->addDays(self::SUMMARY_DAYS - 1))) {
            return [];
        }
        $last = $period->previous();
        // The same money as the home screen: no transfers, nothing charged to a person or a special project.
        $rows = $this->householdMoney($household, $last)->groupBy('category_id')
            ->selectRaw('category_id, sum(case when amount_cents < 0 then -amount_cents else 0 end) as out_cents, sum(case when amount_cents > 0 then amount_cents else 0 end) as in_cents')
            ->get()->keyBy('category_id');
        if ($rows->isEmpty()) {
            return [];
        }
        $categories = Category::withoutGlobalScopes()->where('household_id', $household->id)->get()->keyBy('id');
        $isIncome = fn ($id) => $id !== null && $categories->get($id)?->kind === CategoryKind::Income;
        $moneyIn = (int) $rows->filter(fn ($r) => $isIncome($r->category_id) || $r->category_id === null)->sum('in_cents');
        $spentAll = (int) $rows->reject(fn ($r) => $isIncome($r->category_id))->sum(fn ($r) => $r->out_cents - ($r->category_id === null ? 0 : $r->in_cents));

        $lines = $categories->filter(fn (Category $c) => $c->kind === CategoryKind::Expense && $c->budget_cents > 0);
        $lineSpent = fn (Category $c) => (int) (($rows[$c->id]->out_cents ?? 0) - ($rows[$c->id]->in_cents ?? 0));
        $budgeted = (int) $lines->sum('budget_cents');
        $onLines = (int) $lines->sum($lineSpent);
        $over = $lines->map(fn (Category $c) => ['name' => $c->name, 'over' => $lineSpent($c) - $c->budget_cents])
            ->filter(fn ($l) => $l['over'] > 0)->sortByDesc('over')->values();

        $label = $last->label();
        $body = [];
        if ($budgeted > 0) {
            $title = $onLines > $budgeted
                ? "{$label}: ".money($onLines - $budgeted).' over budget'
                : "{$label}: ".money($budgeted - $onLines).' under budget';
            $body[] = money($onLines).' of '.money($budgeted).' spent on budget lines.';
            if ($over->isNotEmpty()) {
                $names = $over->take(3)->map(fn ($l) => $l['name'].' '.money($l['over']))->implode(', ');
                $body[] = 'Over: '.$names.($over->count() > 3 ? ' and '.($over->count() - 3).' more' : '').'.';
            } else {
                $body[] = 'Every line stayed within its budget.';
            }
        } else {
            $title = "{$label}: ".money($spentAll).' spent';
        }
        $body[] = 'Money in '.money($moneyIn).'; everything spent '.money($spentAll).'.';

        return [new Notice(Notice::SUMMARY, 'summary:'.$last->from->toDateString(), $title, implode(' ', $body),
            route('home', ['in' => $last->from->toDateString()], absolute: false))];
    }

    /**
     * A new statement is out a few days after the last one imported ends, a month later; reminded
     * once then, and once more a week later if it is still not in.
     *
     * @return list<Notice>
     */
    private function statements(Household $household, CarbonImmutable $today): array
    {
        $latest = StatementImport::withoutGlobalScopes()->where('household_id', $household->id)
            ->groupBy('account_id')->selectRaw('account_id, max(period_to) as last_to')->pluck('last_to', 'account_id');
        $accounts = Account::withoutGlobalScopes()->whereIn('id', $latest->keys())->get()->keyBy('id');

        $notices = [];
        foreach ($latest as $accountId => $lastTo) {
            $account = $accounts->get($accountId);
            if ($account === null) {
                continue;
            }
            $expected = CarbonImmutable::parse($lastTo)->addMonthNoOverflow();
            $due = $expected->addDays(self::STATEMENT_OUT_AFTER_DAYS);
            if ($today->lessThan($due)) {
                continue;
            }
            $again = $today->greaterThanOrEqualTo($due->addDays(self::STATEMENT_AGAIN_AFTER_DAYS));
            $notices[] = new Notice(Notice::STATEMENTS,
                'statement:'.$account->id.':'.$expected->toDateString().($again ? ':again' : ''),
                "Upload the {$account->name} statement",
                'The statement to '.$expected->format('j M').' should be out: '.$account->bank->label().' ••'.$account->number_ending.
                    '. Uploading it fills in anything the bank emails missed.',
                route('statements.index', absolute: false));
        }

        return $notices;
    }

    /**
     * The household's own money in a period, as on the home screen: no transfers, nothing charged to a
     * person or a special project.
     *
     * @return Builder<Transaction>
     */
    private function householdMoney(Household $household, BudgetPeriod $period): Builder
    {
        return Transaction::withoutGlobalScopes()->where('household_id', $household->id)
            ->where('is_transfer', false)->whereNull('person_id')->whereNull('project_id')
            ->whereBetween('posted_on', [$period->from->toDateString(), $period->to->toDateString()]);
    }

    /**
     * @param  Collection<int, Category>  $lines
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function spentByLine(Household $household, Collection $lines, BudgetPeriod $period): \Illuminate\Support\Collection
    {
        return $this->householdMoney($household, $period)->whereIn('category_id', $lines->pluck('id'))
            ->groupBy('category_id')->selectRaw('category_id, -sum(amount_cents) as cents')->pluck('cents', 'category_id');
    }

    /** @return list<Notice> */
    private function gmail(Household $household): array
    {
        $notices = [];
        foreach (GmailConnection::withoutGlobalScopes()->where('household_id', $household->id)->where('status', GmailConnection::NEEDS_RELINK)->get() as $connection) {
            $notices[] = new Notice(Notice::GMAIL, "gmail:{$connection->id}:relink",
                'Bank emails have stopped',
                "Google no longer lets Budgeteer read {$connection->email}, so new card payments are not coming in. Link Gmail again in Settings.",
                route('settings', absolute: false).'#gmail');
        }

        return $notices;
    }
}
