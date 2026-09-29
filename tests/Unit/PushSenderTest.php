<?php

use App\Models\PushSubscription;
use App\Notify\PushSender;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\VAPID;
use Psr\Http\Client\ClientInterface;

/** A sender whose push service answers with the given responses and keeps the requests it got. */
function senderAnswering(Response ...$responses): PushSender
{
    return new class($responses) extends PushSender
    {
        public array $requests = [];

        public function __construct(private array $responses) {}

        protected function client(): ClientInterface
        {
            $stack = HandlerStack::create(new MockHandler($this->responses));
            $stack->push(function (callable $next) {
                return function ($request, $options) use ($next) {
                    $this->requests[] = $request;

                    return $next($request, $options);
                };
            });

            return new Client(['handler' => $stack]);
        }
    };
}

function browserSubscription(): PushSubscription
{
    $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $ec = openssl_pkey_get_details($key)['ec'];
    $b64 = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

    return new PushSubscription(['endpoint' => 'https://push.example.test/abc', 'public_key' => $b64("\x04".$ec['x'].$ec['y']), 'auth_token' => $b64(random_bytes(16))]);
}

beforeEach(function () {
    $keys = VAPID::createVapidKeys();
    config(['budgeteer.push' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:budget@example.test']]);
});

it('sends an encrypted, signed notification that Apple, Google and Mozilla accept', function () {
    $sender = senderAnswering(new Response(201));

    expect($sender->send(browserSubscription(), ['title' => 'Late: PPS', 'body' => 'x', 'url' => '/recurring', 'tag' => 'late']))->toBeTrue();

    $request = $sender->requests[0];
    expect((string) $request->getUri())->toBe('https://push.example.test/abc')
        ->and($request->getHeaderLine('Content-Encoding'))->toBe('aes128gcm')
        ->and($request->getHeaderLine('TTL'))->toBe('86400')
        ->and($request->getHeaderLine('Authorization'))->toStartWith('vapid t=')
        ->and((string) $request->getBody())->not->toContain('Late: PPS');
});

it('says so when the phone has turned notifications off', function () {
    expect(senderAnswering(new Response(410))->send(browserSubscription(), ['title' => 't', 'body' => 'b', 'url' => '/', 'tag' => 't']))->toBeFalse();
});
