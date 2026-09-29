<?php

namespace App\Orders;

use App\Gmail\GmailMessage;
use App\Gmail\Parsers\EmailNotUnderstood;
use App\Gmail\Parsers\EmailToIgnore;
use App\Models\Order;

/**
 * Amazon.co.za's "Ordered:" email. Its total is what the card is charged when the order is placed;
 * the "Shipped" and "Delivered" emails show the price before discounts, so they are skipped. Read
 * from the plain-text part when there is one: per item a "* name" line, "Quantity: n" and "189.05 ZAR".
 */
class AmazonEmailParser implements OrderEmailParser
{
    public function recognises(GmailMessage $message): bool
    {
        return (bool) preg_match('/(^|[.@])amazon\.co\.za$/', $message->senderAddress());
    }

    public function parse(GmailMessage $message): ParsedOrder
    {
        if (! preg_match('/^Ordered:/i', trim($message->subject))) {
            throw new EmailToIgnore('An Amazon email that is not an order confirmation.');
        }
        $lines = $message->plainLines !== [] ? $message->plainLines : $message->lines;

        $number = null;
        $total = null;
        $items = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^Order\s*#\s*(\d{3}-\d{7}-\d{7})?$/i', $line, $m)) {
                $number ??= ($m[1] ?? null) ?: (preg_match('/^\d{3}-\d{7}-\d{7}$/', $lines[$i + 1] ?? '') ? $lines[$i + 1] : null);
            } elseif (preg_match('/^Quantity:\s*(\d+)$/i', $line, $m) && $i > 0) {
                $name = trim((string) preg_replace('/^[*•]\s*/u', '', $lines[$i - 1]));
                $items[] = ['name' => mb_substr($name, 0, 300), 'quantity' => (int) $m[1], 'price_cents' => ShopMoney::cents($lines[$i + 1] ?? '')];
            } elseif (preg_match('/^(Order )?Total:?$/i', $line) && isset($lines[$i + 1])) {
                $total = ShopMoney::cents($lines[$i + 1]);
            }
        }

        if ($number === null || $total === null || $items === []) {
            throw new EmailNotUnderstood('An Amazon order email without its order number, items or total.');
        }

        return new ParsedOrder(Order::AMAZON, $number, $message->receivedAt, $total, $items);
    }
}
