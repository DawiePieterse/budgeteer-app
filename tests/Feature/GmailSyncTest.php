<?php

use App\Enums\TransactionSource;
use App\Gmail\GmailSync;
use App\Models\Account;
use App\Models\Card;
use App\Models\GmailConnection;
use App\Models\IngestedEmail;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

/** A Gmail API message holding one made-up Discovery email. */
function gmailMessage(string $id, string $fixture, string $subject, array $replace = []): array
{
    $html = strtr((string) file_get_contents(__DIR__.'/../Fixtures/emails/discovery/'.$fixture.'.html'), $replace);

    return [
        'id' => $id,
        'internalDate' => '1790518754000',
        'payload' => [
            'mimeType' => 'multipart/alternative',
            'headers' => [
                ['name' => 'From', 'value' => 'Discovery Bank <notifications@discovery.bank>'],
                ['name' => 'Subject', 'value' => $subject],
            ],
            'parts' => [
                ['mimeType' => 'text/plain', 'body' => ['data' => rtrim(strtr(base64_encode('plain'), '+/', '-_'), '=')]],
                ['mimeType' => 'text/html', 'body' => ['data' => rtrim(strtr(base64_encode($html), '+/', '-_'), '=')]],
            ],
        ],
    ];
}

/** @param array<string, array> $messages */
function fakeGmail(array $messages, array $historyIds = [], bool $withLabel = true): void
{
    Http::swap(new Factory(app('events'))); // a fresh fake, not stacked behind an earlier one
    Http::fake(function (Request $request) use ($messages, $historyIds, $withLabel) {
        $url = $request->url();

        return match (true) {
            str_contains($url, 'oauth2.googleapis.com/token') => Http::response(['access_token' => 'fresh', 'expires_in' => 3600]),
            str_contains($url, 'oauth2.googleapis.com/revoke') => Http::response([]),
            str_contains($url, '/labels') => Http::response(['labels' => $withLabel ? [['id' => 'Label_7', 'name' => 'Budgeteer'], ['id' => 'INBOX', 'name' => 'INBOX']] : [['id' => 'INBOX', 'name' => 'INBOX']]]),
            str_contains($url, '/profile') => Http::response(['historyId' => '1000']),
            str_contains($url, '/history') => Http::response(['history' => [['messagesAdded' => array_map(fn ($id) => ['message' => ['id' => $id]], $historyIds)]], 'historyId' => '2000']),
            preg_match('#/messages/([^?]+)#', $url, $m) === 1 => isset($messages[$m[1]]) ? Http::response($messages[$m[1]]) : Http::response([], 404),
            str_contains($url, '/messages') => Http::response(['messages' => array_map(fn ($id) => ['id' => $id], array_keys($messages))]),
            default => Http::response([], 500),
        };
    });
}

function linkedGmail(User $user, array $attributes = []): GmailConnection
{
    return GmailConnection::create($attributes + [
        'household_id' => $user->household_id, 'user_id' => $user->id, 'email' => 'bank@example.com',
        'refresh_token' => 'refresh', 'status' => GmailConnection::ACTIVE,
    ]);
}

it('reads bank emails with the label into transactions, with their card', function () {
    $user = member();
    $connection = linkedGmail($user);
    fakeGmail([
        'a' => gmailMessage('a', 'card-payment-extra-card', 'Transaction update — 27 Sep 2026 16:19:14'),
        'b' => gmailMessage('b', 'card-payment-main-card', 'Transaction update — 26 Sep 2026 18:36:00'),
    ]);

    $result = app(GmailSync::class)->sync($connection);

    expect($result)->toMatchArray(['added' => 2, 'matched' => 0, 'finished' => true])
        ->and(Transaction::withoutGlobalScopes()->count())->toBe(2)
        ->and(Account::withoutGlobalScopes()->sole()->number_ending)->toBe('1234')
        ->and(Card::withoutGlobalScopes()->orderBy('number_ending')->pluck('holder_name')->all())->toBe([null, 'Sam Smith'])
        ->and($connection->fresh()->history_id)->toBe('1000')
        ->and($connection->fresh()->label_id)->toBe('Label_7')
        ->and($connection->fresh()->access_token)->toBe('fresh');

    $takealot = Transaction::withoutGlobalScopes()->where('description', 'TAKEALOT CAPE TOWN')->sole();
    expect($takealot->source)->toBe(TransactionSource::Email)
        ->and($takealot->amount_cents)->toBe(-129900)
        ->and($takealot->occurred_at->format('Y-m-d H:i'))->toBe('2026-09-27 16:19')
        ->and($takealot->card->number_ending)->toBe('5678');

    // Only the Budgeteer label is asked for.
    Http::assertSent(fn (Request $r) => str_contains($r->url(), '/messages?') && str_contains($r->url(), 'labelIds=Label_7'));
});

it('reads each email only once, then only new ones from the history', function () {
    $user = member();
    $connection = linkedGmail($user);
    $a = gmailMessage('a', 'card-payment-extra-card', 'Transaction update — 27 Sep 2026 16:19:14');
    fakeGmail(['a' => $a]);
    app(GmailSync::class)->sync($connection);

    fakeGmail(['a' => $a, 'c' => gmailMessage('c', 'card-payment-main-card', 'Transaction update — 28 Sep 2026 09:00:00')], historyIds: ['a', 'c']);
    $result = app(GmailSync::class)->sync($connection->fresh());

    expect($result['added'])->toBe(1)
        ->and(IngestedEmail::withoutGlobalScopes()->count())->toBe(2)
        ->and($connection->fresh()->history_id)->toBe('2000');
});

it('says when the Budgeteer label does not exist yet', function () {
    $connection = linkedGmail(member());
    fakeGmail([], withLabel: false);

    app(GmailSync::class)->sync($connection);

    expect($connection->fresh()->status)->toBe(GmailConnection::LABEL_MISSING);
});

it('asks to link again when Google withdraws the permission', function () {
    $connection = linkedGmail(member());
    Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400)]);

    app(GmailSync::class)->sync($connection);

    expect($connection->fresh()->status)->toBe(GmailConnection::NEEDS_RELINK);
    $this->artisan('budgeteer:gmail-sync')->assertSuccessful(); // skips it without calling Google again
    Http::assertSentCount(1);
});

it('keeps emails it cannot read in the log without stopping', function () {
    $connection = linkedGmail(member());
    $odd = gmailMessage('x', 'card-payment-main-card', 'Transaction update — 26 Sep 2026 18:36:00', ['Card payment' => 'Something new']);
    $other = gmailMessage('y', 'card-payment-main-card', 'Your statement is ready');
    fakeGmail(['x' => $odd, 'y' => $other, 'b' => gmailMessage('b', 'card-payment-main-card', 'Transaction update — 26 Sep 2026 18:36:00')]);

    app(GmailSync::class)->sync($connection);

    expect(IngestedEmail::withoutGlobalScopes()->orderBy('gmail_message_id')->pluck('status', 'gmail_message_id')->all())
        ->toBe(['b' => 'added', 'x' => 'unrecognised', 'y' => 'unrecognised']);
});

it('does not count a purchase twice when its statement arrives after the email', function () {
    $user = member();
    $connection = linkedGmail($user);
    // The made-up statement's account ends in 9999 and has WOOLWORTHS BELLVILLE R356.10 on 1 July.
    fakeGmail(['w' => gmailMessage('w', 'card-payment-main-card', 'Transaction update — 30 Jun 2026 18:36:00', [
        'WOOLWORTHS TYGERVALLEY ZA – R 981.40' => 'WOOLWORTHS TYGERVALLEY ZA – R 356.10', '***1234' => '***9999',
    ])]);
    app(GmailSync::class)->sync($connection);

    $this->actingAs($user)->post('/statements/preview', ['text' => fixtureText('discovery')]);
    $this->post('/statements')->assertSessionHas('status', fn ($s) => str_contains($s, '1 already read from bank emails'));

    $woolworths = Transaction::where('amount_cents', -35610)->sole();
    expect(Transaction::count())->toBe(8)
        ->and($woolworths->source)->toBe(TransactionSource::Email)
        ->and($woolworths->description)->toBe('WOOLWORTHS BELLVILLE')
        ->and($woolworths->posted_on->toDateString())->toBe('2026-07-01')
        ->and($woolworths->card->number_ending)->toBe('4321')
        ->and($woolworths->statement_import_id)->not->toBeNull();
});

it('links a late email to the statement line it belongs to', function () {
    $user = member();
    $this->actingAs($user)->post('/statements/preview', ['text' => fixtureText('discovery')]);
    $this->post('/statements');
    auth()->logout();

    $connection = linkedGmail($user);
    fakeGmail(['w' => gmailMessage('w', 'card-payment-extra-card', 'Transaction update — 30 Jun 2026 12:00:00', [
        'TAKEALOT CAPE TOWN &ndash; R 1,299.00' => 'Woolworths Bellville &ndash; R 356.10', '***1234' => '***9999',
    ])]);
    $result = app(GmailSync::class)->sync($connection);

    expect($result['matched'])->toBe(1)
        ->and(Transaction::withoutGlobalScopes()->count())->toBe(8)
        ->and(Transaction::withoutGlobalScopes()->where('amount_cents', -35610)->sole()->card->holder_name)->toBe('Sam Smith');
});

it('links Gmail with read-only access and reads it straight away', function () {
    $user = member();
    $google = (new GoogleUser)->map(['id' => 'g', 'email' => 'Bank@Example.com'])
        ->setToken('access')->setRefreshToken('refresh')->setExpiresIn(3600)
        ->setApprovedScopes(['openid', 'https://www.googleapis.com/auth/gmail.readonly']);
    Socialite::shouldReceive('driver->redirectUrl->user')->andReturn($google);
    fakeGmail(['a' => gmailMessage('a', 'card-payment-extra-card', 'Transaction update — 27 Sep 2026 16:19:14')]);

    $this->actingAs($user)->get('/gmail/callback?code=x&state=y')
        ->assertRedirect('/settings')
        ->assertSessionHas('status', fn ($s) => str_contains($s, 'is linked') && str_contains($s, 'Read 1 new transaction'));

    $connection = GmailConnection::sole();
    expect($connection->email)->toBe('bank@example.com')
        ->and($connection->getRawOriginal('refresh_token'))->not->toBe('refresh'); // stored encrypted
});

it('refuses a link without permission to read email', function () {
    $user = member();
    $google = (new GoogleUser)->map(['id' => 'g', 'email' => 'bank@example.com'])->setToken('a')->setRefreshToken('r')->setApprovedScopes(['openid']);
    Socialite::shouldReceive('driver->redirectUrl->user')->andReturn($google);

    $this->actingAs($user)->get('/gmail/callback?code=x')->assertSessionHas('error');

    expect(GmailConnection::count())->toBe(0);
});

it('unlinks Gmail and withdraws the permission at Google', function () {
    $user = member();
    $connection = linkedGmail($user);
    Http::fake(['oauth2.googleapis.com/revoke' => Http::response([])]);

    $this->actingAs($user)->post("/gmail/{$connection->id}/delete")->assertRedirect();

    expect(GmailConnection::count())->toBe(0);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'revoke'));
});

it('keeps another household from syncing or unlinking a Gmail link', function () {
    $connection = linkedGmail(member());
    Http::fake();

    $this->actingAs(member());
    $this->post("/gmail/{$connection->id}/sync")->assertNotFound();
    $this->post("/gmail/{$connection->id}/delete")->assertNotFound();

    expect(GmailConnection::withoutGlobalScopes()->count())->toBe(1);
    Http::assertNothingSent();
});

it('shows Gmail, cards and recent emails in settings', function () {
    $user = member();
    $connection = linkedGmail($user);
    fakeGmail(['a' => gmailMessage('a', 'card-payment-extra-card', 'Transaction update — 27 Sep 2026 16:19:14')]);
    app(GmailSync::class)->sync($connection);

    $this->actingAs($user)->get('/settings')
        ->assertSee('bank@example.com')
        ->assertSee('Sam Smith')
        ->assertSee('TAKEALOT CAPE TOWN');
});

it('reads again emails it could not read before', function () {
    $connection = linkedGmail(member());
    $odd = gmailMessage('x', 'card-payment-main-card', 'Transaction update — 26 Sep 2026 18:36:00', ['Card payment' => 'Something new']);
    fakeGmail(['x' => $odd]);
    app(GmailSync::class)->sync($connection);
    expect(IngestedEmail::withoutGlobalScopes()->sole()->status)->toBe('unrecognised');

    // The app has since learnt the new kind; here the email reads normally.
    fakeGmail(['x' => gmailMessage('x', 'card-payment-main-card', 'Transaction update — 26 Sep 2026 18:36:00')]);
    $this->artisan('budgeteer:gmail-sync --retry')->assertSuccessful();

    expect(IngestedEmail::withoutGlobalScopes()->sole()->status)->toBe('added')
        ->and(Transaction::withoutGlobalScopes()->count())->toBe(1);
});
