<?php

namespace App\Recurring;

use App\Models\RecurringMark;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Services\BudgetPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Works out, for a budget period, when each recurring payment was due and whether it was paid,
 * paid a different amount, is still due, or is late.
 */
class RecurringSchedule
{
    /** Days before and after the due date a payment still counts, by frequency. */
    private const WINDOW = [
        RecurringPayment::WEEKLY => [3, 3],
        RecurringPayment::MONTHLY => [5, 7],
        RecurringPayment::YEARLY => [10, 20],
    ];

    /** Days after the due date before a missing payment is late. */
    private const GRACE = [
        RecurringPayment::WEEKLY => 3,
        RecurringPayment::MONTHLY => 5,
        RecurringPayment::YEARLY => 14,
    ];

    /**
     * @param  Collection<int, RecurringPayment>  $payments
     * @return list<Occurrence>
     */
    public function occurrences(Collection $payments, BudgetPeriod $period, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $ids = $payments->pluck('id');
        $transactions = Transaction::withoutGlobalScopes()->whereIn('recurring_payment_id', $ids)
            ->whereBetween('posted_on', [$period->from->subDays(20)->toDateString(), $period->to->addDays(20)->toDateString()])
            ->orderBy('posted_on')->get()->groupBy('recurring_payment_id');
        $marks = RecurringMark::query()->whereIn('recurring_payment_id', $ids)->get()
            ->keyBy(fn (RecurringMark $m) => $m->recurring_payment_id.'|'.$m->due_on->toDateString());

        $occurrences = [];
        foreach ($payments as $payment) {
            $used = [];
            foreach ($this->dueDates($payment, $period) as $dueOn) {
                [$before, $after] = self::WINDOW[$payment->frequency];
                $match = ($transactions[$payment->id] ?? collect())
                    ->reject(fn (Transaction $t) => isset($used[$t->id]))
                    ->filter(fn (Transaction $t) => $t->posted_on->between($dueOn->subDays($before), $dueOn->addDays($after)))
                    ->sortBy(fn (Transaction $t) => abs($t->posted_on->diffInDays($dueOn)))
                    ->first();

                $mark = $marks[$payment->id.'|'.$dueOn->toDateString()] ?? null;
                if ($match !== null) {
                    $used[$match->id] = true;
                    $status = $payment->amountIsExpected($match->amount_cents) ? Occurrence::PAID : Occurrence::CHANGED;
                } elseif ($mark !== null) {
                    $status = $mark->status === RecurringMark::SKIPPED ? Occurrence::SKIPPED : Occurrence::PAID_BY_HAND;
                } else {
                    $status = $today->greaterThan($dueOn->addDays(self::GRACE[$payment->frequency])) ? Occurrence::LATE : Occurrence::DUE;
                }
                $occurrences[] = new Occurrence($payment, $dueOn, $status, $match);
            }
        }

        usort($occurrences, fn (Occurrence $a, Occurrence $b) => [$a->dueOn, $a->payment->name] <=> [$b->dueOn, $b->payment->name]);

        return $occurrences;
    }

    /** @return list<CarbonImmutable> */
    public function dueDates(RecurringPayment $payment, BudgetPeriod $period): array
    {
        $dates = [];
        if ($payment->frequency === RecurringPayment::WEEKLY) {
            $date = $period->from;
            while ($date->dayOfWeekIso !== $payment->day) {
                $date = $date->addDay();
            }
            for (; $date->lessThanOrEqualTo($period->to); $date = $date->addWeek()) {
                $dates[] = $date;
            }

            return $dates;
        }

        for ($month = $period->from->startOfMonth(); $month->lessThanOrEqualTo($period->to); $month = $month->addMonthNoOverflow()) {
            if ($payment->frequency === RecurringPayment::YEARLY && $month->month !== $payment->month) {
                continue;
            }
            $date = $month->setDay(min($payment->day, $month->daysInMonth));
            if ($date->between($period->from, $period->to)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }
}
