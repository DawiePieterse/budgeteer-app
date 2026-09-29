<?php

namespace App\Notify;

/** Something worth telling the household about, sent once per key. */
final class Notice
{
    public const RECURRING = 'recurring';

    public const BUDGET = 'budget';

    public const GMAIL = 'gmail';

    public const SUMMARY = 'summary';

    public const STATEMENTS = 'statements';

    /**
     * @param  string  $key  the same event always has the same key, so it is sent once
     * @param  list<string>  $alsoMarks  keys to record as sent with this one, for example the 80% warning when a line goes straight over
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $key,
        public readonly string $title,
        public readonly string $body,
        public readonly string $url,
        public readonly array $alsoMarks = [],
    ) {}
}
