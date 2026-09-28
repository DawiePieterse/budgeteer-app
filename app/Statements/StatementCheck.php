<?php

namespace App\Statements;

/**
 * A statement is only imported if every line adds up: the opening balance plus each amount
 * gives the balance printed on that line, and the totals printed on the statement (where it
 * has them) match the lines read. A misread line is caught here, before anything is saved.
 */
class StatementCheck
{
    public function verify(ParsedStatement $statement): void
    {
        $balance = $statement->openingCents;
        foreach ($statement->lines as $line) {
            $balance += $line->amountCents;
            if ($balance !== $line->balanceCents) {
                throw new StatementDoesNotAddUp(sprintf(
                    'Line %d (%s, %s) should leave a balance of %s, but the statement shows %s.',
                    $line->number, $line->date->format('j M Y'), $line->description,
                    Money::format($balance), Money::format($line->balanceCents),
                ));
            }
        }

        if ($balance !== $statement->closingCents) {
            throw new StatementDoesNotAddUp('The transactions do not add up to the closing balance.');
        }
        if ($statement->printedMoneyOutCents !== null && $statement->printedMoneyOutCents !== $statement->moneyOutCents()) {
            throw new StatementDoesNotAddUp(sprintf('The statement shows %s paid out, but the lines read add up to %s.', Money::format($statement->printedMoneyOutCents), Money::format($statement->moneyOutCents())));
        }
        if ($statement->printedMoneyInCents !== null && $statement->printedMoneyInCents !== $statement->moneyInCents()) {
            throw new StatementDoesNotAddUp(sprintf('The statement shows %s paid in, but the lines read add up to %s.', Money::format($statement->printedMoneyInCents), Money::format($statement->moneyInCents())));
        }
    }
}
