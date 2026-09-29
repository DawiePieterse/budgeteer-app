<?php

namespace App\Orders;

use App\Gmail\GmailMessage;
use App\Gmail\Parsers\EmailNotUnderstood;
use App\Gmail\Parsers\EmailToIgnore;
use App\Models\Order;

/**
 * Takealot's "Payment Confirmation" email, sent the moment the card is charged: order number in the
 * subject, then per item its quantity, name and price, and the total.
 */
class TakealotEmailParser implements OrderEmailParser
{
    public function recognises(GmailMessage $message): bool
    {
        return (bool) preg_match('/(^|[.@])takealot\.com$/', $message->senderAddress());
    }

    public function parse(GmailMessage $message): ParsedOrder
    {
        if (! preg_match('/Payment Confirmation\s*\|\s*(\d{6,})/i', $message->subject, $m)) {
            throw new EmailToIgnore('A Takealot email that is not an order payment.');
        }
        $number = $m[1];
        $lines = $message->lines;

        $total = null;
        $deliverTo = null;
        $items = [];
        $inItems = false;
        foreach ($lines as $i => $line) {
            if (preg_match('/^Deliver To:?$/i', $line) && isset($lines[$i + 1])) {
                // Only the name: the address that follows is not kept.
                $deliverTo = trim((string) preg_replace('/\s*\d.*$/', '', $lines[$i + 1])) ?: null;
            } elseif (preg_match('/^Items in this order$/i', $line)) {
                $inItems = true;
            } elseif (preg_match('/^Subtotal:?$/i', $line)) {
                $inItems = false;
            } elseif (preg_match('/^Total:?$/i', $line) && isset($lines[$i + 1])) {
                $total = self::rand($lines[$i + 1]);
            } elseif ($inItems && preg_match('/^\d{1,3}$/', $line) && isset($lines[$i + 2]) && self::rand($lines[$i + 2]) !== null) {
                $items[] = ['name' => mb_substr($lines[$i + 1], 0, 300), 'quantity' => (int) $line, 'price_cents' => self::rand($lines[$i + 2])];
            }
        }

        if ($total === null || $items === []) {
            throw new EmailNotUnderstood('A Takealot payment confirmation without its items or total.');
        }

        return new ParsedOrder(Order::TAKEALOT, $number, $message->receivedAt, $total, $items, $deliverTo);
    }

    /** "R 199.00"; a bare number is the quantity column, not a price. */
    private static function rand(string $text): ?int
    {
        return str_starts_with(trim($text), 'R') ? ShopMoney::cents($text) : null;
    }
}
