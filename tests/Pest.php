<?php

use App\Models\GmailConnection;
use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdSetup;
use App\Statements\StatementText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

function fixtureText(string $name): string
{
    return (string) file_get_contents(__DIR__.'/Fixtures/statements/'.$name.'.json');
}

function fixtureStatement(string $name): StatementText
{
    return StatementText::fromArray(json_decode(fixtureText($name), true));
}

/** A signed-in member of a household with the default categories. */
function member(array $household = []): User
{
    $h = Household::factory()->create($household);
    app(HouseholdSetup::class)->createDefaultCategories($h);

    return User::factory()->create(['household_id' => $h->id]);
}

/** A Gmail API message holding one made-up Discovery email. */
function gmailMessage(string $id, string $fixture, string $subject, array $replace = []): array
{
    $html = strtr((string) file_get_contents(__DIR__.'/Fixtures/emails/discovery/'.$fixture.'.html'), $replace);

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
