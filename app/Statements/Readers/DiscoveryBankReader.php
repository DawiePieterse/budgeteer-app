<?php

namespace App\Statements\Readers;

use App\Enums\AccountKind;
use App\Enums\Bank;
use App\Statements\Money;
use App\Statements\ParsedStatement;
use App\Statements\StatementLine;
use App\Statements\StatementText;
use App\Statements\UnreadableStatement;
use Carbon\CarbonImmutable;

/**
 * Discovery Bank credit card statement. One row per transaction: ISO date, description,
 * amount in the Debit or Credit column, and the balance (money available, so purchases
 * lower it). Which column an amount is in is known only from its position on the page.
 */
class DiscoveryBankReader implements StatementReader
{
    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    public function recognises(StatementText $text): bool
    {
        return $text->contains('Discovery Bank Limited') || $text->contains('Discovery Place');
    }

    public function read(StatementText $text): ParsedStatement
    {
        $from = $to = $accountNumber = $creditColumnFrom = null;
        $lines = [];

        foreach ($text->rows() as $row) {
            $joined = StatementText::join($row);

            if (preg_match('/From:\s*(\d{4}-\d{2}-\d{2})\s*To:\s*(\d{4}-\d{2}-\d{2})/', $joined, $m)) {
                $from = CarbonImmutable::parse($m[1])->startOfDay();
                $to = CarbonImmutable::parse($m[2])->startOfDay();
            }
            if (preg_match('/Account number:\s*(\d+)/', $joined, $m)) {
                $accountNumber = $m[1];
            }
            if (($row[0]['s'] ?? '') === 'Date' && str_contains($joined, 'Debit') && str_contains($joined, 'Credit')) {
                $debit = $this->x($row, 'Debit');
                $credit = $this->x($row, 'Credit');
                // Amounts are right-aligned under their headings: anything starting right of the
                // midpoint between the two headings is a credit.
                $creditColumnFrom = ($debit + $credit) / 2;

                continue;
            }
            if (preg_match(self::DATE, $row[0]['s'] ?? '') !== 1 || $creditColumnFrom === null) {
                continue;
            }

            $amounts = array_values(array_filter($row, fn ($item) => Money::isAmount($item['s'])));
            if (count($amounts) < 2) {
                throw new UnreadableStatement('Could not read the amount and balance of: '.$joined);
            }
            $amount = $amounts[count($amounts) - 2];
            $words = array_filter(array_slice($row, 1), fn ($item) => ! Money::isAmount($item['s']));
            $cents = abs(Money::toCents($amount['s']));

            $lines[] = new StatementLine(
                count($lines) + 1,
                CarbonImmutable::parse($row[0]['s'])->startOfDay(),
                trim(implode(' ', array_column($words, 's'))),
                null,
                $amount['x'] >= $creditColumnFrom ? $cents : -$cents,
                Money::toCents($amounts[count($amounts) - 1]['s']),
            );
        }

        if ($from === null || $to === null || $accountNumber === null) {
            throw new UnreadableStatement('This does not look like a Discovery Bank statement: the period or account number is missing.');
        }
        if ($lines === []) {
            throw new UnreadableStatement('No transactions were found on this statement.');
        }

        $opening = $lines[0]->balanceCents - $lines[0]->amountCents;

        return new ParsedStatement(
            Bank::Discovery, AccountKind::CreditCard, substr($accountNumber, -4), $from, $to, $opening,
            end($lines)->balanceCents, $lines,
        );
    }

    /** @param list<array{x: float, s: string}> $row */
    private function x(array $row, string $text): float
    {
        foreach ($row as $item) {
            if ($item['s'] === $text) {
                return $item['x'];
            }
        }

        throw new UnreadableStatement("Column {$text} not found.");
    }
}
