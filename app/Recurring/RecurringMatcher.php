<?php

namespace App\Recurring;

use App\Models\Household;
use App\Models\RecurringPayment;
use App\Models\Transaction;

/**
 * Links payments out to the recurring payment they belong to, by the words in their description,
 * and gives them its budget line when they have none yet. Runs after every statement import,
 * Gmail sync and change to a recurring payment.
 */
class RecurringMatcher
{
    public function link(int $householdId): int
    {
        $payments = RecurringPayment::withoutGlobalScopes()->where('household_id', $householdId)->where('active', true)->get();
        if ($payments->isEmpty()) {
            return 0;
        }
        $keepFrom = Household::query()->whereKey($householdId)->value('keep_from');

        $linked = 0;
        Transaction::withoutGlobalScopes()
            ->where('household_id', $householdId)
            ->whereNull('recurring_payment_id')
            ->where('amount_cents', '<', 0)
            ->where('is_transfer', false)
            ->whereNull('person_id')
            ->when($keepFrom, fn ($q) => $q->where('posted_on', '>=', $keepFrom))
            ->orderBy('posted_on')
            ->each(function (Transaction $t) use ($payments, &$linked) {
                $payment = $payments->first(fn (RecurringPayment $p) => $p->matches($t->description, $t->merchant_key));
                if ($payment === null) {
                    return;
                }
                $t->update([
                    'recurring_payment_id' => $payment->id,
                    'category_id' => $t->category_id ?? $payment->category_id,
                ]);
                $linked++;
            });

        return $linked;
    }

    /** Unlinks a payment's transactions, before its match text changes or it is deleted. */
    public function unlink(RecurringPayment $payment): void
    {
        Transaction::withoutGlobalScopes()->where('recurring_payment_id', $payment->id)->update(['recurring_payment_id' => null]);
    }
}
