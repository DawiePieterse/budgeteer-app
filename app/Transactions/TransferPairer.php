<?php

namespace App\Transactions;

use App\Models\Transaction;

/**
 * Links the two halves of money moved between the household's own accounts, for example the
 * credit card repayment: a payment out of the cheque account and a payment into the card
 * account of the same amount within a few days.
 */
class TransferPairer
{
    public const DAYS = 3;

    public function pair(int $householdId): int
    {
        $unpaired = Transaction::withoutGlobalScopes()
            ->where('household_id', $householdId)
            ->where('is_transfer', true)
            ->whereNull('transfer_pair_id')
            ->orderBy('posted_on')
            ->orderBy('id')
            ->get();

        $paired = 0;
        $used = [];
        foreach ($unpaired->where('amount_cents', '<', 0) as $out) {
            $in = $unpaired->first(fn (Transaction $t) => ! isset($used[$t->id])
                && $t->amount_cents === -$out->amount_cents
                && $t->account_id !== $out->account_id
                && abs($t->posted_on->diffInDays($out->posted_on)) <= self::DAYS);
            if ($in === null) {
                continue;
            }
            $used[$in->id] = true;
            $out->update(['transfer_pair_id' => $in->id]);
            $in->update(['transfer_pair_id' => $out->id]);
            $paired++;
        }

        return $paired;
    }
}
