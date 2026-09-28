<?php

namespace App\Gmail\Parsers;

use App\Enums\Bank;
use App\Enums\TransactionKind;
use App\Gmail\GmailMessage;
use App\Statements\Money;
use Carbon\CarbonImmutable;

/**
 * Discovery Bank "Transaction update" emails. The body is a short list of lines:
 *
 *   Card payment
 *   Checkers Sixty60 Cape To – R 469.88
 *   From ***4813
 *   Dewan Pieterse            (only on extra cards; left out for the main cardholder)
 *   Card ending ***2720
 *   Sunday, 27 September at 16:19
 *   Available balance: R 165,371.88
 *
 * The subject carries the full date and time: "Transaction update — 27 Sep 2026 16:19:14".
 */
class DiscoveryEmailParser implements EmailParser
{
    private const MERCHANT_AND_AMOUNT = '/^(.+?)\s+[–—-]\s+(R\s?[\d,]+\.\d{2})$/u';

    public function recognises(GmailMessage $message): bool
    {
        return str_contains($message->senderAddress(), 'discovery')
            && str_starts_with(strtolower($message->subject), 'transaction update');
    }

    public function parse(GmailMessage $message): ParsedEmail
    {
        $lines = $message->lines;
        $index = $this->find($lines, self::MERCHANT_AND_AMOUNT);
        if ($index === null) {
            throw new EmailNotUnderstood('No amount found.');
        }
        preg_match(self::MERCHANT_AND_AMOUNT, $lines[$index], $m);
        $description = trim($m[1]);
        $cents = abs(Money::toCents($m[2]));
        $heading = $index > 0 ? $lines[$index - 1] : '';

        $kind = $this->kind($heading);
        $accountEnding = $this->ending($lines, '/^From\s+\**(\d{4})\b/i');
        if ($accountEnding === null) {
            throw new EmailNotUnderstood('No account number found.');
        }
        $cardEnding = $this->ending($lines, '/^Card ending\s+\**(\d{4})\b/i');

        // The cardholder's name, when there is one, is the line between "From" and "Card ending".
        $cardholder = null;
        $from = $this->find($lines, '/^From\s+\**\d{4}/i');
        $card = $this->find($lines, '/^Card ending/i');
        if ($from !== null && $card !== null && $card - $from === 2) {
            $cardholder = $lines[$from + 1];
        }

        $balance = null;
        $balanceIndex = $this->find($lines, '/^Available balance:\s*R/i');
        if ($balanceIndex !== null && preg_match('/(R\s?-?[\d,]+\.\d{2})/', $lines[$balanceIndex], $b) === 1) {
            $balance = Money::toCents($b[1]);
        }

        return new ParsedEmail(
            Bank::Discovery,
            $kind,
            $description,
            in_array($kind, [TransactionKind::Refund, TransactionKind::Deposit], true) ? $cents : -$cents,
            $accountEnding,
            $cardEnding,
            $cardholder,
            $this->when($message),
            $balance,
        );
    }

    private function kind(string $heading): TransactionKind
    {
        $h = strtolower($heading);

        return match (true) {
            str_contains($h, 'declin') || str_contains($h, 'unsuccessful') || str_contains($h, 'reversal') => throw new EmailToIgnore("Not a completed transaction: {$heading}"),
            str_contains($h, 'refund') => TransactionKind::Refund,
            str_contains($h, 'withdrawal') || str_contains($h, 'atm') => TransactionKind::Cash,
            str_contains($h, 'received') || str_contains($h, 'deposit') || str_contains($h, 'money in') => TransactionKind::Deposit,
            str_contains($h, 'card payment') || str_contains($h, 'purchase') || str_contains($h, 'online payment') => TransactionKind::Purchase,
            default => throw new EmailNotUnderstood('Unknown kind of notification: '.mb_substr($heading, 0, 100)),
        };
    }

    private function when(GmailMessage $message): CarbonImmutable
    {
        if (preg_match('/(\d{1,2} [A-Za-z]{3} \d{4} \d{2}:\d{2}(?::\d{2})?)/', $message->subject, $m) === 1) {
            return CarbonImmutable::parse($m[1], config('app.timezone'));
        }

        return $message->receivedAt;
    }

    /** @param list<string> $lines */
    private function find(array $lines, string $pattern): ?int
    {
        foreach ($lines as $i => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $i;
            }
        }

        return null;
    }

    /** @param list<string> $lines */
    private function ending(array $lines, string $pattern): ?string
    {
        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }
}
