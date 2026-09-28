<?php

namespace App\Statements;

use App\Statements\Readers\DiscoveryBankReader;
use App\Statements\Readers\StandardBankReader;
use App\Statements\Readers\StatementReader;

class StatementReaders
{
    /** @var list<StatementReader> */
    private array $readers;

    public function __construct(StandardBankReader $standardBank, DiscoveryBankReader $discovery, private StatementCheck $check)
    {
        $this->readers = [$standardBank, $discovery];
    }

    /** Reads and checks a statement from any supported bank. */
    public function read(StatementText $text): ParsedStatement
    {
        foreach ($this->readers as $reader) {
            if ($reader->recognises($text)) {
                $statement = $reader->read($text);
                $this->check->verify($statement);

                return $statement;
            }
        }

        throw new UnreadableStatement('This statement is not from Standard Bank or Discovery Bank, which are the banks Budgeteer can read.');
    }
}
