<?php

use App\Gmail\GmailSync;
use App\Models\Account;
use App\Models\IngestedEmail;
use App\Models\Order;
use App\Models\Person;
use App\Models\Transaction;
use App\Orders\OrderMatcher;
use App\Orders\OrderRecorder;
use App\Orders\ParsedOrder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/** A Gmail API message from a shop, with an HTML part and, optionally, a plain-text one. */
function shopGmailMessage(string $id, string $from, string $subject, string $html, string $plain = 'plain'): array
{
    $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

    return [
        'id' => $id,
        'internalDate' => '1790518750000', // 27 Sep 2026 16:19:10 SAST
        'payload' => [
            'mimeType' => 'multipart/alternative',
            'headers' => [['name' => 'From', 'value' => $from], ['name' => 'Subject', 'value' => $subject]],
            'parts' => [
                ['mimeType' => 'text/plain', 'body' => ['data' => $b64($plain)]],
                ['mimeType' => 'text/html', 'body' => ['data' => $b64($html)]],
            ],
        ],
    ];
}

function takealotOrderEmail(string $id = 'shop1'): array
{
    return shopGmailMessage($id, 'Takealot <info@takealot.com>', 'Takealot | Payment Confirmation | 229999001',
        (string) file_get_contents(__DIR__.'/../Fixtures/emails/shops/takealot-payment-confirmation.html'));
}

afterEach(fn () => Carbon::setTestNow());

it('links a Takealot order to its card payment and shows the items', function () {
    Carbon::setTestNow('2026-09-27 18:00');
    $user = member();
    $connection = linkedGmail($user);
    fakeGmail([
        'shop1' => takealotOrderEmail(),
        'bank1' => gmailMessage('bank1', 'card-payment-extra-card', 'Transaction update — 27 Sep 2026 16:19:14'), // TAKEALOT R1,299.00
    ]);

    app(GmailSync::class)->sync($connection);

    $order = Order::withoutGlobalScopes()->with('items')->sole();
    $payment = Transaction::withoutGlobalScopes()->sole();
    expect($order->transaction_id)->toBe($payment->id)
        ->and($order->items->pluck('name')->all())->toBe(['Philips Kettle 1.7L', 'USB-C Cable 1m'])
        ->and(IngestedEmail::withoutGlobalScopes()->where('gmail_message_id', 'shop1')->sole()->status)->toBe('order');

    $this->actingAs($user);
    $this->get('/transactions')->assertSee('Philips Kettle 1.7L, USB-C Cable 1m ×2');
    $this->get('/transactions?q=kettle')->assertSee('Takealot')->assertSee('1 transaction');
    $this->get('/transactions?q=toaster')->assertSee('No transactions.');
    $this->get("/transactions/{$payment->id}")
        ->assertSee('Takealot order 229999001')->assertSee('R899.00')->assertSee('× 2')
        ->assertSee('delivered to Sam Smith')->assertDontSee('Example Street')
        ->assertSee('https://www.takealot.com/account/orders/229999001', false);
});

it('suggests charging an order to the person it was delivered to', function () {
    Carbon::setTestNow('2026-09-27 18:00');
    $user = member();
    Person::create(['household_id' => $user->household_id, 'name' => 'Sam']);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $payment = Transaction::factory()->for($account)->create(['posted_on' => '2026-09-27', 'amount_cents' => -129900, 'description' => 'TAKEALOT CAPE TOWN']);
    app(OrderRecorder::class)->record(new ParsedOrder('takealot', '229999001', CarbonImmutable::parse('2026-09-27 16:19'), 129900,
        [['name' => 'Philips Kettle 1.7L', 'quantity' => 1, 'price_cents' => 129900]], 'Sam Smith'), $user->household_id);
    app(OrderMatcher::class)->link($user->household_id);

    $this->actingAs($user)->get("/transactions/{$payment->id}")->assertSee('This went to Sam.');
    $payment->update(['person_id' => Person::sole()->id]);
    $this->get("/transactions/{$payment->id}")->assertDontSee('This went to Sam.');
});

it('links an order whose payment only arrives later, and never two orders to one payment', function () {
    Carbon::setTestNow('2026-09-30 09:00');
    $user = member();
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $order = fn (string $number, string $at, int $cents) => app(OrderRecorder::class)->record(
        new ParsedOrder('amazon', $number, CarbonImmutable::parse($at), $cents, [['name' => 'Item '.$number, 'quantity' => 1, 'price_cents' => $cents]]), $user->household_id);
    $first = $order('171-0000000-0000001', '2026-09-22 14:42', 53910);
    $second = $order('171-0000000-0000002', '2026-09-22 15:10', 53910);   // the same amount again
    $other = $order('171-0000000-0000003', '2026-09-22 16:00', 12000);

    expect(app(OrderMatcher::class)->link($user->household_id))->toBe(0);

    // From the statement, days later: two Amazon lines of R539.10, a Takealot one of R120.00 and a late one.
    $a = Transaction::factory()->for($account)->create(['posted_on' => '2026-09-23', 'amount_cents' => -53910, 'description' => 'DL*AMAZON RETAIL CPT']);
    $b = Transaction::factory()->for($account)->create(['posted_on' => '2026-09-24', 'amount_cents' => -53910, 'description' => 'DL*AMAZON RETAIL CPT']);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-09-22', 'amount_cents' => -12000, 'description' => 'TAKEALOT CAPE TOWN']);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-10-05', 'amount_cents' => -12000, 'description' => 'DL*AMAZON RETAIL CPT']);

    expect(app(OrderMatcher::class)->link($user->household_id))->toBe(2)
        ->and([$first->fresh()->transaction_id, $second->fresh()->transaction_id])->toEqualCanonicalizing([$a->id, $b->id])
        ->and($other->fresh()->transaction_id)->toBeNull();
});

it('keeps an order once, however many emails mention it', function () {
    $user = member();
    $parsed = new ParsedOrder('takealot', '229999001', CarbonImmutable::parse('2026-09-27 16:19'), 129900, [['name' => 'Kettle', 'quantity' => 1, 'price_cents' => 129900]]);
    app(OrderRecorder::class)->record($parsed, $user->household_id);
    app(OrderRecorder::class)->record($parsed, $user->household_id);

    expect(Order::withoutGlobalScopes()->count())->toBe(1)->and(Order::withoutGlobalScopes()->sole()->items()->count())->toBe(1);
});
