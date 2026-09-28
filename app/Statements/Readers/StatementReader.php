<?php

namespace App\Statements\Readers;

use App\Statements\ParsedStatement;
use App\Statements\StatementText;

interface StatementReader
{
    public function recognises(StatementText $text): bool;

    public function read(StatementText $text): ParsedStatement;
}
