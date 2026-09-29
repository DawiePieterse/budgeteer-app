<?php

namespace App\Recurring;

use App\Models\Household;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

/**
 * Finds payments out that come back every month or every week at a similar amount, and are not
 * set up as recurring payments yet.
 */
class RecurringSuggestions
{
    /** Largest amount divided by smallest that still counts as "a similar amount". */
    private const SIMILAR = 1.5;

    /**
     * @return list<array{name: string, match_text: string, category_id: int|null, amount_cents: int, amount_varies: bool, frequency: string, day: int, seen: int}>
     */
    public function for(int $householdId, ?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $keepFrom = Household::query()->whereKey($householdId)->value('keep_from');
        $since = $today->subDays(120)->startOfMonth();
        if ($keepFrom !== null && CarbonImmutable::parse($keepFrom)->greaterThan($since)) {
            $since = CarbonImmutable::parse($keepFrom);
        }
        $months = max(1, (int) ceil($since->diffInMonths($today)));

        $covered = RecurringPayment::withoutGlobalScopes()->where('household_id', $householdId)->pluck('match_text')->map(fn ($m) => mb_strtoupper($m))->all();
        $groups = Transaction::withoutGlobalScopes()
            ->where('household_id', $householdId)
            ->where('amount_cents', '<', 0)
            ->where('is_transfer', false)
            ->whereNull('person_id')
            ->whereNull('project_id')
            ->whereNull('recurring_payment_id')
            ->where('posted_on', '>=', $since->toDateString())
            ->whereNotIn('merchant_key', ['UNKNOWN', 'BANK FEES', 'CASH', 'OWN ACCOUNTS', 'INTEREST'])
            ->get()
            ->groupBy('merchant_key');

        $suggestions = [];
        foreach ($groups as $key => $group) {
            if (in_array(mb_strtoupper((string) $key), $covered, true)) {
                continue;
            }
            $amounts = $group->map(fn (Transaction $t) => -$t->amount_cents)->sort()->values();
            if ($amounts->first() <= 0 || $amounts->last() / $amounts->first() > self::SIMILAR) {
                continue;
            }
            $monthsSeen = $group->map(fn (Transaction $t) => $t->posted_on->format('Y-m'))->unique()->count();
            $perMonth = $group->count() / max(1, $monthsSeen);
            $latest = $group->sortBy('posted_on')->last();

            if ($monthsSeen >= min(3, $months) && $monthsSeen >= 2 && $perMonth <= 1.5) {
                $frequency = RecurringPayment::MONTHLY;
                $days = $group->map(fn (Transaction $t) => $t->posted_on->day)->sort()->values();
                $day = (int) $days[intdiv($days->count(), 2)];
            } elseif ($monthsSeen >= 2 && $perMonth >= 3.5) {
                $frequency = RecurringPayment::WEEKLY;
                $day = (int) $group->countBy(fn (Transaction $t) => $t->posted_on->dayOfWeekIso)->sortDesc()->keys()->first();
            } else {
                continue;
            }

            $suggestions[] = [
                'name' => (string) $key,
                'match_text' => (string) $key,
                'category_id' => $group->pluck('category_id')->filter()->countBy()->sortDesc()->keys()->first(),
                'amount_cents' => -$latest->amount_cents,
                'amount_varies' => $amounts->last() / $amounts->first() > 1.02,
                'frequency' => $frequency,
                'day' => $day,
                'seen' => $group->count(),
            ];
        }

        usort($suggestions, fn ($a, $b) => $b['amount_cents'] <=> $a['amount_cents']);

        return $suggestions;
    }
}
