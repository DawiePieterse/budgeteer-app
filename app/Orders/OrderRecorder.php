<?php

namespace App\Orders;

use App\Models\Order;

/** Saves an order once (a second email about the same order changes nothing). */
class OrderRecorder
{
    public function record(ParsedOrder $parsed, int $householdId, ?int $ingestedEmailId = null): Order
    {
        $order = Order::withoutGlobalScopes()->firstOrCreate(
            ['household_id' => $householdId, 'shop' => $parsed->shop, 'order_number' => $parsed->orderNumber],
            ['ordered_at' => $parsed->orderedAt, 'total_cents' => $parsed->totalCents, 'deliver_to' => $parsed->deliverTo, 'ingested_email_id' => $ingestedEmailId],
        );
        if ($order->wasRecentlyCreated) {
            foreach ($parsed->items as $item) {
                $order->items()->create($item);
            }
        }

        return $order;
    }
}
