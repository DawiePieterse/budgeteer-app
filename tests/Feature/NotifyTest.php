<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\GmailConnection;
use App\Models\Household;
use App\Models\PushSubscription;
use App\Models\RecurringPayment;
use App\Models\SentNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Notify\PushSender;
use Illuminate\Support\Carbon;

/** A push service that keeps what it was given; subscriptions named "gone" report the phone as removed. */
function fakePush(): object
{
    $fake = new class extends PushSender
    {
        /** @var list<array{device: string|null, title: string, body: string, url: string}> */
        public array $sent = [];

        public function configured(): bool
        {
            return true;
        }

        public function send(PushSubscription $subscription, array $message): bool
        {
            if ($subscription->device === 'gone') {
                return false;
            }
            $this->sent[] = ['device' => $subscription->device] + $message;

            return true;
        }
    };
    app()->instance(PushSender::class, $fake);

    return $fake;
}

function phone(User $user, string $device = 'Android Chrome'): PushSubscription
{
    $endpoint = 'https://push.example.test/'.uniqid();

    return PushSubscription::create(['user_id' => $user->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hash($endpoint),
        'public_key' => 'BKey', 'auth_token' => 'auth', 'device' => $device]);
}

function budgetLine(User $user, string $name, int $budgetCents): Category
{
    $category = Category::withoutGlobalScopes()->where('household_id', $user->household_id)->where('name', $name)->sole();
    $category->update(['budget_cents' => $budgetCents]);

    return $category;
}

function spend(Account $account, Category $category, string $on, int $cents, array $extra = []): Transaction
{
    return Transaction::factory()->for($account)->create(['posted_on' => $on, 'amount_cents' => -$cents, 'category_id' => $category->id] + $extra);
}

afterEach(fn () => Carbon::setTestNow());

it('warns once at 80% and once when a budget line goes over', function () {
    Carbon::setTestNow('2026-09-20 09:00');
    $push = fakePush();
    $user = member();
    phone($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = budgetLine($user, 'Groceries', 500000);
    budgetLine($user, 'Medical', 900000); // keeps the whole budget under
    spend($account, $groceries, '2026-09-05', 420000);
    spend($account, $groceries, '2026-08-25', 300000); // last month, not counted

    $this->artisan('budgeteer:notify')->assertSuccessful();
    $this->artisan('budgeteer:notify')->assertSuccessful();
    expect($push->sent)->toHaveCount(1)
        ->and($push->sent[0]['title'])->toBe('Groceries at 84%')
        ->and($push->sent[0]['body'])->toBe('R4,200.00 of R5,000.00 spent: R800.00 left, 11 days to go.')
        ->and($push->sent[0]['url'])->toBe("/transactions?category={$groceries->id}&month=2026-09-01");

    spend($account, $groceries, '2026-09-19', 100000);
    $this->artisan('budgeteer:notify');
    expect($push->sent)->toHaveCount(2)
        ->and($push->sent[1]['title'])->toBe('Groceries is over budget')
        ->and($push->sent[1]['body'])->toContain('R200.00 over (104%)');
});

it('does not count money moved between own accounts towards the budget', function () {
    Carbon::setTestNow('2026-09-20 09:00');
    $push = fakePush();
    $user = member();
    phone($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $groceries = budgetLine($user, 'Groceries', 100000);
    spend($account, $groceries, '2026-09-05', 90000, ['is_transfer' => true]);

    $this->artisan('budgeteer:notify');
    expect($push->sent)->toBe([]);
});

it('goes straight to over budget without a separate 80% warning', function () {
    Carbon::setTestNow('2026-09-20 09:00');
    $push = fakePush();
    $user = member();
    phone($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $fuel = budgetLine($user, 'Fuel and car', 100000);
    spend($account, $fuel, '2026-09-05', 120000);

    $this->artisan('budgeteer:notify');
    expect($push->sent)->toHaveCount(1)   // the line only; nothing else has a budget, so the total is the same money
        ->and($push->sent[0]['title'])->toBe('2 budget warnings')
        ->and($push->sent[0]['body'])->toBe("Fuel and car is over budget\nThe whole budget is over")
        ->and(SentNotification::pluck('key')->sort()->values()->all())->toBe(['near:'.$fuel->id.':2026-09-01', 'over:'.$fuel->id.':2026-09-01', 'over:total:2026-09-01']);
});

it('tells about late and changed recurring payments', function () {
    Carbon::setTestNow('2026-09-15 09:00');
    $push = fakePush();
    $user = member();
    phone($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $pps = RecurringPayment::withoutGlobalScopes()->create(['household_id' => $user->household_id, 'name' => 'PPS', 'match_text' => 'PPS', 'amount_cents' => 600000, 'frequency' => 'monthly', 'day' => 1]);
    RecurringPayment::withoutGlobalScopes()->create(['household_id' => $user->household_id, 'name' => 'Levies', 'match_text' => 'LEVY', 'amount_cents' => 164100, 'frequency' => 'monthly', 'day' => 3]);
    Transaction::factory()->for($account)->create(['posted_on' => '2026-09-08', 'amount_cents' => -863102, 'description' => 'PPS 123', 'merchant_key' => 'PPS', 'recurring_payment_id' => $pps->id]);
    $levies = RecurringPayment::withoutGlobalScopes()->where('name', 'Levies')->sole();
    Transaction::factory()->for($account)->create(['posted_on' => '2026-08-03', 'amount_cents' => -164100, 'description' => 'LEVY', 'merchant_key' => 'LEVY', 'recurring_payment_id' => $levies->id]);

    $this->artisan('budgeteer:notify');

    expect($push->sent)->toHaveCount(1)
        ->and($push->sent[0]['title'])->toBe('3 recurring payments need a look')
        // August's PPS never went off; September's did, but more than expected; September's levies are late.
        ->and($push->sent[0]['body'])->toBe("Late: PPS\nAmount changed: PPS\nLate: Levies")
        ->and($push->sent[0]['url'])->toBe('/recurring');

    $this->artisan('budgeteer:notify');
    expect($push->sent)->toHaveCount(1);
});

it('keeps old news quiet: payments late for weeks, or changed long ago', function () {
    Carbon::setTestNow('2026-09-28 09:00');
    $push = fakePush();
    $user = member();
    phone($user);
    $account = Account::factory()->create(['household_id' => $user->household_id]);
    $pps = RecurringPayment::withoutGlobalScopes()->create(['household_id' => $user->household_id, 'name' => 'PPS', 'match_text' => 'PPS', 'amount_cents' => 600000, 'frequency' => 'monthly', 'day' => 1]);
    foreach (['2026-08-01', '2026-09-01'] as $on) {
        Transaction::factory()->for($account)->create(['posted_on' => $on, 'amount_cents' => -863102, 'description' => 'PPS 123', 'merchant_key' => 'PPS', 'recurring_payment_id' => $pps->id]);
    }

    $this->artisan('budgeteer:notify');
    expect($push->sent)->toBe([]);
});

it('sends only the kinds each person wants, to each of their phones', function () {
    Carbon::setTestNow('2026-09-20 09:00');
    $push = fakePush();
    $me = member();
    $wife = User::factory()->create(['household_id' => $me->household_id, 'notify_budget' => false]);
    phone($me, 'Android Chrome');
    phone($me, 'Windows Edge');
    phone($wife, 'iPhone Safari');
    $account = Account::factory()->create(['household_id' => $me->household_id]);
    spend($account, budgetLine($me, 'Groceries', 100000), '2026-09-05', 90000);
    GmailConnection::withoutGlobalScopes()->create(['household_id' => $me->household_id, 'user_id' => $me->id, 'email' => 'me@example.test', 'refresh_token' => 'x', 'status' => GmailConnection::NEEDS_RELINK]);

    $this->artisan('budgeteer:notify');

    expect(collect($push->sent)->map(fn ($m) => $m['device'].': '.$m['title'])->sort()->values()->all())->toBe([
        'Android Chrome: Bank emails have stopped',
        'Android Chrome: Groceries at 90%',
        'Windows Edge: Bank emails have stopped',
        'Windows Edge: Groceries at 90%',
        'iPhone Safari: Bank emails have stopped',
    ]);
});

it('warns again when Gmail breaks a second time', function () {
    Carbon::setTestNow('2026-09-20 09:00');
    $push = fakePush();
    $user = member();
    phone($user);
    $connection = GmailConnection::withoutGlobalScopes()->create(['household_id' => $user->household_id, 'user_id' => $user->id, 'email' => 'me@example.test', 'refresh_token' => 'x', 'status' => GmailConnection::NEEDS_RELINK]);

    $this->artisan('budgeteer:notify');
    $this->artisan('budgeteer:notify');
    $connection->update(['status' => GmailConnection::ACTIVE]);
    $this->artisan('budgeteer:notify');
    $connection->update(['status' => GmailConnection::NEEDS_RELINK]);
    $this->artisan('budgeteer:notify');

    expect($push->sent)->toHaveCount(2);
});

it('forgets a phone that no longer takes notifications', function () {
    Carbon::setTestNow('2026-09-20 09:00');
    fakePush();
    $user = member();
    phone($user, 'gone');
    $kept = phone($user);
    $this->actingAs($user)->post('/push/test')->assertSessionHas('status', 'Test sent to 1 phone.');

    expect(PushSubscription::pluck('id')->all())->toBe([$kept->id]);
});

it('saves and forgets this phone, and saves choices', function () {
    $user = member();
    $this->actingAs($user);
    $body = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'BKey', 'auth' => 'auth'], 'device' => 'Android Chrome'];

    $this->postJson('/push/subscriptions', $body)->assertOk();
    $this->postJson('/push/subscriptions', $body)->assertOk();
    expect(PushSubscription::count())->toBe(1);
    $this->postJson('/push/subscriptions', ['endpoint' => 'http://evil.test/'] + $body)->assertUnprocessable();

    $this->get('/settings')->assertSee('Phone notifications')->assertSee('Android Chrome');
    $this->post('/push/preferences', ['notify_recurring' => '1'])->assertRedirect();
    expect($user->fresh()->only(['notify_recurring', 'notify_budget', 'notify_gmail']))->toBe(['notify_recurring' => true, 'notify_budget' => false, 'notify_gmail' => false]);

    $this->postJson('/push/subscriptions/delete', ['endpoint' => $body['endpoint']])->assertOk();
    expect(PushSubscription::count())->toBe(0);
});

it('does not let one person remove another person\'s phone', function () {
    $user = member();
    $other = User::factory()->create(['household_id' => Household::factory()->create()->id]);
    $theirs = phone($other);

    $this->actingAs($user)->post("/push/subscriptions/{$theirs->id}/delete")->assertNotFound();
    expect($theirs->fresh())->not->toBeNull();
});
