<?php

namespace App\Gmail;

use Carbon\CarbonImmutable;

final class GmailMessage
{
    public function __construct(
        public readonly string $id,
        public readonly string $from,
        public readonly string $subject,
        public readonly CarbonImmutable $receivedAt,
        /** The body as plain lines of text, from the HTML or plain-text part. */
        public readonly array $lines,
    ) {}

    /** The address part of From, lower case: "Discovery Bank <alerts@discovery.bank>" gives alerts@discovery.bank. */
    public function senderAddress(): string
    {
        return strtolower(preg_match('/<([^>]+)>/', $this->from, $m) === 1 ? $m[1] : trim($this->from));
    }
}
