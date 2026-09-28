<?php

namespace App\Gmail\Parsers;

use App\Enums\Bank;
use App\Enums\TransactionKind;
use App\Gmail\GmailMessage;
use App\Statements\Money;
use Carbon\CarbonImmutable;

/**
 * Standard Bank "MyUpdates Notification" emails, one sentence per transaction:
 *
 *   An amount of R200.00 was paid from Standard Bank account ending in 3445 to ANN PIETERSE ANN SALARIS on 2026-09-28.
 *   The actual balance at the time of the above transaction was R17303.27.
 */
class StandardBankEmailParser implements EmailParser
{
    private const OUT = '/An amount of (R\s?[\d,]+\.\d{2}) was (?:paid|debited|withdrawn|transferred) from .*?account ending in (\d{4}) (?:to|for|at) (.+?) on (\d{4}-\d{2}-\d{2})/i';

    private const IN = '/An amount of (R\s?[\d,]+\.\d{2}) was (?:paid|deposited|credited|received|transferred) (?:into|to) .*?account ending in (\d{4})(?: from| by)? (.+?) on (\d{4}-\d{2}-\d{2})/i';

    public function recognises(GmailMessage $message): bool
    {
        return str_ends_with($message->senderAddress(), 'standardbank.co.za')
            && stripos($message->subject, 'MyUpdates') !== false;
    }

    public function parse(GmailMessage $message): ParsedEmail
    {
        $text = implode(' ', $message->lines);

        foreach ([[self::OUT, -1], [self::IN, 1]] as [$pattern, $sign]) {
            if (preg_match($pattern, $text, $m) === 1) {
                $when = CarbonImmutable::parse($m[4], config('app.timezone'))->setTimeFrom($message->receivedAt);

                return new ParsedEmail(
                    Bank::StandardBank,
                    $sign < 0 ? TransactionKind::Payment : TransactionKind::Deposit,
                    trim($m[3]),
                    $sign * abs(Money::toCents($m[1])),
                    $m[2],
                    null,
                    null,
                    $when,
                );
            }
        }

        throw new EmailNotUnderstood('Not a kind of Standard Bank notification Budgeteer can read yet.');
    }
}
