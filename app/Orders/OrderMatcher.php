<?php

namespace App\Orders;

use App\Models\Order;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * Links each order to the card payment for it: the same shop, exactly the order total, from the day
 * before to a few days after the order (a statement can post it later than the email).
 */
class OrderMatcher
{
    public const DAYS_AFTER = 5;

    /** What the shop is called on a bank line. */
    private const DESCRIPTION = [Order::TAKEALOT => 'TAKEALOT', Order::AMAZON => 'AMAZON'];

    /** @return int orders linked */
    public function link(int $householdId): int
    {
        $linked = 0;
        $orders = Order::withoutGlobalScopes()->where('household_id', $householdId)->whereNull('transaction_id')
            ->where('ordered_at', '>=', now()->subDays(120))->orderBy('ordered_at')->get();
        foreach ($orders as $order) {
            $taken = Order::withoutGlobalScopes()->whereNotNull('transaction_id')->select('transaction_id');
            $day = $order->ordered_at->copy()->startOfDay();
            $match = Transaction::withoutGlobalScopes()->where('household_id', $householdId)
                ->where('amount_cents', -$order->total_cents)
                ->where(DB::raw('upper(description)'), 'like', '%'.self::DESCRIPTION[$order->shop].'%')
                ->whereBetween('posted_on', [$day->copy()->subDay()->toDateString(), $day->copy()->addDays(self::DAYS_AFTER)->toDateString()])
                ->whereNotIn('id', $taken)
                ->get()
                ->sortBy(fn (Transaction $t) => abs(($t->occurred_at ?? $t->posted_on)->getTimestamp() - $order->ordered_at->getTimestamp()))
                ->first();
            if ($match !== null) {
                $order->update(['transaction_id' => $match->id]);
                $linked++;
            }
        }

        return $linked;
    }
}
