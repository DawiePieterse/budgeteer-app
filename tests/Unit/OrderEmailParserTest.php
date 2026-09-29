<?php

use App\Gmail\GmailMessage;
use App\Gmail\HtmlText;
use App\Gmail\Parsers\EmailToIgnore;
use App\Orders\AmazonEmailParser;
use App\Orders\ShopMoney;
use App\Orders\TakealotEmailParser;
use Carbon\CarbonImmutable;

function shopEmail(string $from, string $subject, string $html = '', string $plain = ''): GmailMessage
{
    return new GmailMessage('m1', $from, $subject, CarbonImmutable::parse('2026-09-27 16:19:10'), HtmlText::lines($html), HtmlText::plain($plain));
}

function shopFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/emails/shops/'.$name);
}

it('reads a Takealot payment confirmation: items, total and only the name it goes to', function () {
    $parser = new TakealotEmailParser;
    $email = shopEmail('Takealot <info@takealot.com>', 'Takealot | Payment Confirmation | 229999001', shopFixture('takealot-payment-confirmation.html'));

    $order = $parser->parse($email);

    expect($parser->recognises($email))->toBeTrue()
        ->and($order->shop)->toBe('takealot')
        ->and($order->orderNumber)->toBe('229999001')
        ->and($order->totalCents)->toBe(129900)
        ->and($order->deliverTo)->toBe('Sam Smith')
        ->and($order->items)->toBe([
            ['name' => 'Philips Kettle 1.7L', 'quantity' => 1, 'price_cents' => 89900],
            ['name' => 'USB-C Cable 1m', 'quantity' => 2, 'price_cents' => 40000],
        ]);
});

it('skips Takealot emails that are not an order payment', function (string $subject) {
    (new TakealotEmailParser)->parse(shopEmail('info@transactions.takealot.com', $subject, '<p>Hi</p>'));
})->throws(EmailToIgnore::class)->with([
    'Hi Sam, review your products and you could WIN a R1000 voucher!',
    'TakealotMORE | Payment confirmation',
]);

it('reads an Amazon.co.za order from its plain-text part, total as charged', function () {
    $parser = new AmazonEmailParser;
    $email = shopEmail('"Amazon.co.za" <auto-confirm@amazon.co.za>', 'Ordered: 2 ‘Body Cream for Dry Skin...’ and 4 more items', '<div>busy html</div>', shopFixture('amazon-ordered.txt'));

    $order = $parser->parse($email);

    expect($parser->recognises($email))->toBeTrue()
        ->and($order->shop)->toBe('amazon')
        ->and($order->orderNumber)->toBe('171-1234567-7654321')
        ->and($order->totalCents)->toBe(53910)
        ->and($order->deliverTo)->toBeNull()
        ->and($order->items)->toBe([
            ['name' => 'Body Cream for Dry Skin, 450ml', 'quantity' => 2, 'price_cents' => 18905],
            ['name' => 'Toothpaste 75 ml', 'quantity' => 3, 'price_cents' => 5499],
            ['name' => 'Rolled Oats, 1kg', 'quantity' => 1, 'price_cents' => 3514],
        ]);
});

it('skips Amazon shipped, delivered and refund emails, whose totals are not what was charged', function (string $subject) {
    (new AmazonEmailParser)->parse(shopEmail('shipment-tracking@amazon.co.za', $subject, '', shopFixture('amazon-ordered.txt')));
})->throws(EmailToIgnore::class)->with(['Shipped: 2 ‘Body Cream…’', 'Delivered: ‘Rolled Oats…’', 'Your refund for Toothpaste….']);

it('does not take other senders', function () {
    expect((new AmazonEmailParser)->recognises(shopEmail('orders@amazon.com', 'Ordered: x')))->toBeFalse()
        ->and((new TakealotEmailParser)->recognises(shopEmail('info@nottakealot.com.evil.test', 'x')))->toBeFalse();
});

it('reads amounts the way shops write them', function (string $text, ?int $cents) {
    expect(ShopMoney::cents($text))->toBe($cents);
})->with([
    ['R 1 299.00', 129900], ['R1,299.00', 129900], ['539.1 ZAR', 53910], ['35.14 ZAR', 3514], ['R 0.00', 0], ['Quantity: 2', null],
]);
