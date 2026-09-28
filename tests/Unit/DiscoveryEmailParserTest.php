<?php

use App\Enums\TransactionKind;
use App\Gmail\GmailMessage;
use App\Gmail\HtmlText;
use App\Gmail\Parsers\DiscoveryEmailParser;
use App\Gmail\Parsers\EmailNotUnderstood;
use App\Gmail\Parsers\EmailToIgnore;
use Carbon\CarbonImmutable;

function discoveryEmail(string $html, string $subject = 'Transaction update — 27 Sep 2026 16:19:14'): GmailMessage
{
    return new GmailMessage('m1', 'Discovery Bank <notifications@discovery.bank>', $subject, CarbonImmutable::parse('2026-09-27 16:19:20'), HtmlText::lines($html));
}

function emailFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/emails/discovery/'.$name.'.html');
}

it('turns email HTML into its visible lines', function () {
    expect(HtmlText::lines('<style>x{}</style><p>Card&nbsp;payment</p><div>A<br>B</div>'))->toBe(['Card payment', 'A', 'B']);
});

it('reads a purchase on an extra card, with the cardholder', function () {
    $e = (new DiscoveryEmailParser)->parse(discoveryEmail(emailFixture('card-payment-extra-card')));

    expect($e->kind)->toBe(TransactionKind::Purchase)
        ->and($e->description)->toBe('TAKEALOT CAPE TOWN')
        ->and($e->amountCents)->toBe(-129900)
        ->and($e->accountEnding)->toBe('1234')
        ->and($e->cardEnding)->toBe('5678')
        ->and($e->cardholder)->toBe('Sam Smith')
        ->and($e->occurredAt->format('Y-m-d H:i:s'))->toBe('2026-09-27 16:19:14')
        ->and($e->availableBalanceCents)->toBe(16537188);
});

it('reads a purchase on the main card, which names no cardholder', function () {
    $e = (new DiscoveryEmailParser)->parse(discoveryEmail(emailFixture('card-payment-main-card'), 'Transaction update — 26 Sep 2026 18:36:00'));

    expect($e->description)->toBe('WOOLWORTHS TYGERVALLEY ZA')
        ->and($e->amountCents)->toBe(-98140)
        ->and($e->cardEnding)->toBe('4321')
        ->and($e->cardholder)->toBeNull()
        ->and($e->occurredAt->toDateString())->toBe('2026-09-26');
});

it('recognises only Discovery transaction updates', function () {
    $parser = new DiscoveryEmailParser;
    expect($parser->recognises(discoveryEmail('')))->toBeTrue()
        ->and($parser->recognises(discoveryEmail('', 'Your statement is ready')))->toBeFalse()
        ->and($parser->recognises(new GmailMessage('x', 'Someone <a@example.com>', 'Transaction update', CarbonImmutable::now(), [])))->toBeFalse();
});

it('reads refunds as money in', function () {
    $html = str_replace('Card payment', 'Card refund', emailFixture('card-payment-main-card'));

    $e = (new DiscoveryEmailParser)->parse(discoveryEmail($html));

    expect($e->kind)->toBe(TransactionKind::Refund)->and($e->amountCents)->toBe(98140);
});

it('skips declined purchases', function () {
    (new DiscoveryEmailParser)->parse(discoveryEmail(str_replace('Card payment', 'Card payment declined', emailFixture('card-payment-main-card'))));
})->throws(EmailToIgnore::class);

it('says so when it meets a kind of email it does not know', function () {
    (new DiscoveryEmailParser)->parse(discoveryEmail(str_replace('Card payment', 'Something new', emailFixture('card-payment-main-card'))));
})->throws(EmailNotUnderstood::class, 'Something new');

it('reads ATM withdrawals, which say "From account ending"', function () {
    $e = (new DiscoveryEmailParser)->parse(discoveryEmail(emailFixture('atm-withdrawal'), 'Transaction Update — 24 Aug 2026 16:30:06'));

    expect($e->kind)->toBe(TransactionKind::Cash)
        ->and($e->description)->toBe('Cnr Main Rd and Station St M')
        ->and($e->amountCents)->toBe(-10000)
        ->and($e->accountEnding)->toBe('1234')
        ->and($e->cardEnding)->toBe('5678')
        ->and($e->cardholder)->toBe('Sam Smith')
        ->and($e->availableBalanceCents)->toBe(16586032);
});
