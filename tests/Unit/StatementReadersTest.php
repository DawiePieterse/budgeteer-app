<?php

use App\Enums\AccountKind;
use App\Enums\Bank;
use App\Statements\Money;
use App\Statements\StatementCheck;
use App\Statements\StatementDoesNotAddUp;
use App\Statements\StatementReaders;
use App\Statements\StatementText;
use App\Statements\UnreadableStatement;

it('reads amounts in the forms the banks print them', function (string $text, int $cents) {
    expect(Money::toCents($text))->toBe($cents);
})->with([
    ['R 356.10', 35610],
    ['R 104,226.36', 10422636],
    ['-40,000.00', -4000000],
    ['-R564,550.73', -56455073],
    ['R558,282.43', 55828243],
    ['23,771.57', 2377157],
]);

it('reads a Standard Bank statement over several pages', function () {
    $s = app(StatementReaders::class)->read(fixtureStatement('standard-bank'));

    expect($s->bank)->toBe(Bank::StandardBank)
        ->and($s->accountKind)->toBe(AccountKind::Cheque)
        ->and($s->accountNumberEnding)->toBe('5678')
        ->and($s->from->toDateString())->toBe('2026-07-01')
        ->and($s->to->toDateString())->toBe('2026-08-30')
        ->and($s->openingCents)->toBe(100000)
        ->and($s->closingCents)->toBe(984050)
        ->and($s->lines)->toHaveCount(6)
        ->and($s->printedMoneyOutCents)->toBe(-415950)
        ->and($s->printedMoneyInCents)->toBe(1300000);

    $first = $s->lines[0];
    expect($first->date->toDateString())->toBe('2026-07-02')
        ->and($first->description)->toBe('J SMITH DISCOVERY BA')
        ->and($first->bankType)->toBe('IB-BETALING NA')
        ->and($first->amountCents)->toBe(-50000)
        ->and($first->balanceCents)->toBe(50000)
        ->and($s->lines[3]->description)->toBe('VASTE MAANDELIKSE FOOI');
});

it('reads a Discovery statement, taking debit or credit from the column', function () {
    $s = app(StatementReaders::class)->read(fixtureStatement('discovery'));

    expect($s->bank)->toBe(Bank::Discovery)
        ->and($s->accountKind)->toBe(AccountKind::CreditCard)
        ->and($s->accountNumberEnding)->toBe('9999')
        ->and($s->openingCents)->toBe(1000000)
        ->and($s->closingCents)->toBe(749133)
        ->and(array_map(fn ($l) => $l->amountCents, $s->lines))
        ->toBe([-35610, -4500, 50000, 5610, -7000, 633, -10000, -250000]);
});

/** Replaces the text of one item in the fixture, found by its current text. */
function withItem(string $fixture, string $from, string $to): StatementText
{
    $json = json_decode(fixtureText($fixture), true);
    array_walk_recursive($json, function (&$value) use ($from, $to) {
        if ($value === $from) {
            $value = $to;
        }
    });

    return StatementText::fromArray($json);
}

it('refuses a statement whose lines do not add up', function () {
    $text = withItem('standard-bank', '-500.00', '-501.00'); // a misread payment

    app(StatementReaders::class)->read($text);
})->throws(StatementDoesNotAddUp::class, 'Line 1');

it('refuses a statement whose printed totals differ from the lines', function () {
    app(StatementReaders::class)->read(withItem('standard-bank', '-R4,159.50', '-R4,160.50'));
})->throws(StatementDoesNotAddUp::class, 'paid out');

it('refuses statements from other banks', function () {
    app(StatementReaders::class)->read(StatementText::fromArray(['pages' => [[['y' => 1, 'items' => [['x' => 1, 's' => 'Some other bank']]]]]]));
})->throws(UnreadableStatement::class);

it('rejects text that is not a statement', function () {
    StatementText::fromArray(['pages' => 'nope']);
})->throws(InvalidArgumentException::class);

it('has a check that passes a statement read correctly', function () {
    $s = app(StatementReaders::class)->read(fixtureStatement('discovery'));
    (new StatementCheck)->verify($s);
    expect(true)->toBeTrue();
});
