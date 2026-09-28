<?php

namespace App\Gmail\Parsers;

use App\Gmail\GmailMessage;

interface EmailParser
{
    public function recognises(GmailMessage $message): bool;

    /**
     * @throws EmailNotUnderstood
     * @throws EmailToIgnore
     */
    public function parse(GmailMessage $message): ParsedEmail;
}
