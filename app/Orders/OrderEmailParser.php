<?php

namespace App\Orders;

use App\Gmail\GmailMessage;
use App\Gmail\Parsers\EmailNotUnderstood;
use App\Gmail\Parsers\EmailToIgnore;

interface OrderEmailParser
{
    public function recognises(GmailMessage $message): bool;

    /**
     * @throws EmailNotUnderstood
     * @throws EmailToIgnore for the shop's other emails: shipped, delivered, reviews, offers
     */
    public function parse(GmailMessage $message): ParsedOrder;
}
