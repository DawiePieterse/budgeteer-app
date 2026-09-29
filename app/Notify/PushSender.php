<?php

namespace App\Notify;

use App\Models\PushSubscription;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

/** Sends one notification to one phone or browser through its push service (Google, Apple or Mozilla). */
class PushSender
{
    public function configured(): bool
    {
        return config('budgeteer.push.public_key') && config('budgeteer.push.private_key');
    }

    /**
     * @param  array{title: string, body: string, url: string, tag: string}  $message
     * @return bool false when the phone no longer accepts notifications, so the subscription can go
     */
    public function send(PushSubscription $subscription, array $message): bool
    {
        $push = new WebPush(
            ['VAPID' => [
                'subject' => config('budgeteer.push.subject'),
                'publicKey' => config('budgeteer.push.public_key'),
                'privateKey' => config('budgeteer.push.private_key'),
            ]],
            ['TTL' => 86400, 'urgency' => 'normal'],
            $this->client(),
            logger: logger(), // hosting without GMP or BCMath only gets a note in the log, not an error
        );

        $report = $push->sendOneNotification(Subscription::create([
            'endpoint' => $subscription->endpoint,
            'keys' => ['p256dh' => $subscription->public_key, 'auth' => $subscription->auth_token],
            'contentEncoding' => 'aes128gcm', // what Apple requires and every current browser reads
        ]), (string) json_encode($message));

        if (! $report->isSuccess() && ! $report->isSubscriptionExpired()) {
            report(new \RuntimeException('Push failed: '.$report->getReason()));
        }

        return ! $report->isSubscriptionExpired();
    }

    protected function client(): ClientInterface
    {
        return new Client(['timeout' => 20, 'connect_timeout' => 10]);
    }
}
