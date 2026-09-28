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
 * Standard Bank account statement (PDF from online banking). Each transaction is a row with
 * date, payee, amount (payments printed negative) and running balance, followed by a row
 * with the transaction type, in the account's language.
 */
class StandardBankReader implements StatementReader
{
    private const MONTHS = [
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'mrt' => 3, 'apr' => 4, 'may' => 5, 'mei' => 5, 'jun' => 6,
        'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'okt' => 10, 'nov' => 11, 'dec' => 12, 'des' => 12,
    ];

    private const DATE = '/^(\d{1,2}) ([A-Za-z]{3}) (\d{2})$/';

    public function recognises(StatementText $text): bool
    {
        return $text->contains('standardbank.co.za') || $text->contains('The Standard Bank of South Africa');
    }

    public function read(StatementText $text): ParsedStatement
    {
        $from = $to = $accountNumber = $opening = $printedOut = $printedIn = null;
        $kind = AccountKind::Cheque;
        $lines = [];
        $inBody = false;
        $current = null;

        foreach ($text->pages as $page) {
            $inBody = false;
            foreach ($page as $row) {
                $joined = StatementText::join($row);
                $first = $row[0]['s'] ?? '';

                if (preg_match('/^From:\s*(.+)$/', $joined, $m)) {
                    $from ??= $this->date(trim($m[1]));
                } elseif (preg_match('/^To:\s*(.+)$/', $joined, $m)) {
                    $to ??= $this->date(trim($m[1]));
                } elseif ($first === 'Account number:' && isset($row[1])) {
                    $accountNumber ??= preg_replace('/\D/', '', $row[1]['s']);
                } elseif ($first === 'Product name:' && isset($row[1]) && preg_match('/MONEY ?MARKET|MARKETLINK/i', $row[1]['s'])) {
                    $kind = AccountKind::MoneyMarket;
                } elseif ($first === 'Payments' && isset($row[1]) && Money::isAmount($row[1]['s'])) {
                    $printedOut = -abs(Money::toCents($row[1]['s']));
                } elseif ($first === 'Deposits' && isset($row[1]) && Money::isAmount($row[1]['s'])) {
                    $printedIn = abs(Money::toCents($row[1]['s']));
                }

                if ($first === 'Date' && str_contains($joined, 'Balance')) {
                    $inBody = true;

                    continue;
                }
                if (! $inBody) {
                    continue;
                }
                if (str_starts_with($joined, 'Please verify') || str_starts_with($joined, 'The Standard Bank of South Africa') || str_starts_with($joined, 'Statement Summary')) {
                    $inBody = false;

                    continue;
                }

                if (str_contains($joined, 'STATEMENT OPENING BALANCE')) {
                    $opening = Money::toCents(end($row)['s']);

                    continue;
                }

                if (preg_match(self::DATE, $first) === 1) {
                    $current = $this->transaction($row, count($lines) + 1);
                    $lines[] = $current;

                    continue;
                }

                // A row under a transaction without a date is its transaction type.
                if ($current !== null && count($lines) > 0) {
                    $last = array_pop($lines);
                    $lines[] = [...$last, 'type' => trim(($last['type'] ?? '').' '.$joined)];
                    $current = null;
                }
            }
        }

        if ($from === null || $to === null || $accountNumber === null || $accountNumber === '' || $opening === null) {
            throw new UnreadableStatement('This does not look like a Standard Bank statement: the period, account number or opening balance is missing.');
        }
        if ($lines === []) {
            throw new UnreadableStatement('No transactions were found on this statement.');
        }

        $statementLines = array_map(fn (array $l) => new StatementLine(
            $l['number'], $l['date'], $l['description'], $l['type'] ?? null, $l['amount'], $l['balance'],
        ), $lines);

        return new ParsedStatement(
            Bank::StandardBank, $kind, substr($accountNumber, -4), $from, $to, $opening,
            end($statementLines)->balanceCents, $statementLines, $printedOut, $printedIn,
        );
    }

    /**
     * @param  list<array{x: float, s: string}>  $row
     * @return array{number: int, date: CarbonImmutable, description: string, amount: int, balance: int}
     */
    private function transaction(array $row, int $number): array
    {
        $amounts = array_values(array_filter($row, fn ($item) => Money::isAmount($item['s'])));
        if (count($amounts) < 2) {
            throw new UnreadableStatement('Could not read the amount and balance of: '.StatementText::join($row));
        }
        $words = array_filter(array_slice($row, 1), fn ($item) => ! Money::isAmount($item['s']));

        return [
            'number' => $number,
            'date' => $this->date($row[0]['s']),
            'description' => trim(implode(' ', array_column($words, 's'))),
            'amount' => Money::toCents($amounts[count($amounts) - 2]['s']),
            'balance' => Money::toCents($amounts[count($amounts) - 1]['s']),
        ];
    }

    private function date(string $text): CarbonImmutable
    {
        if (preg_match(self::DATE, $text, $m) !== 1 || ! isset(self::MONTHS[strtolower($m[2])])) {
            throw new UnreadableStatement("Could not read the date {$text}.");
        }

        return CarbonImmutable::create(2000 + (int) $m[3], self::MONTHS[strtolower($m[2])], (int) $m[1])->startOfDay();
    }
}
