<?php

namespace App\Notify;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\GmailConnection;
use App\Models\Household;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Recurring\Occurrence;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
use Carbon\CarbonImmutable;

/** Looks at a household's money as it is now and lists everything that could be worth a notification. */
class NoticeFinder
{
    /** A budget line gets a warning at this share of its budget, and another when it goes over. */
    public const WARN_AT = 0.8;

    /** Late payments older than this are no longer news. */
    private const LATE_DAYS = 45;

    /** A changed amount is only news while the payment is this recent. */
    private const CHANGED_DAYS = 10;

    public function __construct(private RecurringSchedule $schedule) {}

    /** @return list<Notice> */
    public function find(Household $household, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $period = BudgetPeriod::containing($today, $household->period_start_day);

        return [
            ...$this->recurring($household, $period, $today),
            ...$this->budget($household, $period, $today),
            ...$this->gmail($household),
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
        // The same money as the home screen: no transfers, nothing charged to a person or a special project.
        $spent = Transaction::withoutGlobalScopes()->where('household_id', $household->id)
            ->where('is_transfer', false)->whereNull('person_id')->whereNull('project_id')
            ->whereBetween('posted_on', [$period->from->toDateString(), $period->to->toDateString()])
            ->whereIn('category_id', $lines->pluck('id'))
            ->groupBy('category_id')->selectRaw('category_id, -sum(amount_cents) as cents')->pluck('cents', 'category_id');

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
