<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Person;
use App\Models\Settlement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a person owes: the opening balance typed in, plus everything charged to them after that
 * date (refunds reduce it), less what they paid back.
 */
class PersonBalance
{
    public function owed(Person $person): int
    {
        $charged = -(int) $this->charges($person)->sum('amount_cents');
        $paid = (int) Settlement::withoutGlobalScopes()->where('person_id', $person->id)->sum('amount_cents');

        return $person->opening_balance_cents + $charged - $paid;
    }

    /**
     * Transactions that add to what the person owes: charged to them, after the opening balance date,
     * and not their own repayments.
     *
     * @return Builder<Transaction>
     */
    public function charges(Person $person): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->where('person_id', $person->id)
            ->when($person->opening_balance_on, fn (Builder $q) => $q->where('posted_on', '>', $person->opening_balance_on))
            ->whereNotIn('id', Settlement::withoutGlobalScopes()->whereNotNull('transaction_id')->select('transaction_id'));
    }

    /**
     * Payments into our accounts that look like they came from the person, not yet recorded.
     *
     * @return Collection<int, Transaction>
     */
    public function possibleRepayments(Person $person)
    {
        $reference = strtoupper(trim((string) $person->payment_reference));
        if ($reference === '') {
            return new Collection;
        }

        return Transaction::withoutGlobalScopes()
            ->where('household_id', $person->household_id)
            ->where('amount_cents', '>', 0)
            ->where('is_transfer', false)
            ->whereNull('person_id')
            ->where('posted_on', '>=', now()->subDays(180)->toDateString())
            ->where(DB::raw('upper(description)'), 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $reference).'%')
            ->whereNotIn('id', Settlement::withoutGlobalScopes()->whereNotNull('transaction_id')->select('transaction_id'))
            ->orderByDesc('posted_on')
            ->get();
    }

    /** Charges every transaction on the card to its person, or back to the household when it has none. */
    public function applyCard(Card $card): int
    {
        return Transaction::withoutGlobalScopes()
            ->where('card_id', $card->id)
            ->whereNotIn('id', Settlement::withoutGlobalScopes()->whereNotNull('transaction_id')->select('transaction_id'))
            ->update(['person_id' => $card->charge_to_person_id]);
    }
}
