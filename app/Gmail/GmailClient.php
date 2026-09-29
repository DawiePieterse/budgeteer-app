<?php

namespace App\Gmail;

use App\Models\GmailConnection;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The few Gmail API calls Budgeteer needs, read-only, through Laravel's HTTP client.
 */
class GmailClient
{
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Withdraws the permission at Google, so the stored token can never be used again. */
    public function revoke(GmailConnection $connection): void
    {
        Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $connection->refresh_token]);
    }

    public function labelId(GmailConnection $connection, string $name): ?string
    {
        $labels = $this->get($connection, '/labels')->json('labels') ?? [];
        foreach ($labels as $label) {
            if (strcasecmp((string) ($label['name'] ?? ''), $name) === 0) {
                return (string) $label['id'];
            }
        }

        return null;
    }

    public function currentHistoryId(GmailConnection $connection): string
    {
        return (string) $this->get($connection, '/profile')->json('historyId');
    }

    /**
     * Message IDs with the label, newest first, for the first sync.
     *
     * @return list<string>
     */
    public function messageIds(GmailConnection $connection, string $labelId, string $query, int $max): array
    {
        $ids = [];
        $pageToken = null;
        do {
            $response = $this->get($connection, '/messages', array_filter([
                'labelIds' => $labelId, 'q' => $query, 'maxResults' => min(100, $max), 'pageToken' => $pageToken,
            ]));
            foreach ($response->json('messages') ?? [] as $message) {
                $ids[] = (string) $message['id'];
            }
            $pageToken = $response->json('nextPageToken');
        } while ($pageToken !== null && count($ids) < $max);

        return array_slice($ids, 0, $max);
    }

    /**
     * Messages that got the label since the given history ID.
     *
     * @return array{ids: list<string>, historyId: string}|null null when the history ID is too old
     */
    public function labelledSince(GmailConnection $connection, string $labelId, string $historyId): ?array
    {
        $ids = [];
        $latest = $historyId;
        $pageToken = null;
        do {
            $response = $this->request($connection)->get(self::API.'/history', array_filter([
                'startHistoryId' => $historyId, 'labelId' => $labelId, 'pageToken' => $pageToken,
                'historyTypes' => 'messageAdded', 'maxResults' => 500,
            ]));
            if ($response->status() === 404) {
                return null;
            }
            $this->throwIfFailed($response);

            foreach ($response->json('history') ?? [] as $entry) {
                foreach ($entry['messagesAdded'] ?? [] as $added) {
                    $ids[] = (string) $added['message']['id'];
                }
                foreach ($entry['labelsAdded'] ?? [] as $added) {
                    $ids[] = (string) $added['message']['id'];
                }
            }
            $latest = (string) ($response->json('historyId') ?? $latest);
            $pageToken = $response->json('nextPageToken');
        } while ($pageToken !== null);

        return ['ids' => array_values(array_unique($ids)), 'historyId' => $latest];
    }

    /** The message, or null when it no longer exists. */
    public function message(GmailConnection $connection, string $id): ?GmailMessage
    {
        $response = $this->request($connection)->get(self::API.'/messages/'.$id, ['format' => 'full']);
        if ($response->status() === 404) {
            return null;
        }
        $this->throwIfFailed($response);
        $data = $response->json();
        $headers = [];
        foreach ($data['payload']['headers'] ?? [] as $header) {
            $headers[strtolower((string) $header['name'])] = (string) $header['value'];
        }

        $html = $this->part($data['payload'] ?? [], 'text/html');
        $plain = HtmlText::plain((string) $this->part($data['payload'] ?? [], 'text/plain'));
        $lines = $html !== null ? HtmlText::lines($html) : $plain;

        return new GmailMessage(
            $id,
            $headers['from'] ?? '',
            $headers['subject'] ?? '',
            CarbonImmutable::createFromTimestampMs((int) ($data['internalDate'] ?? 0))->setTimezone(config('app.timezone')),
            $lines,
            $plain,
        );
    }

    /** @param array<string, mixed> $payload */
    private function part(array $payload, string $mimeType): ?string
    {
        if (($payload['mimeType'] ?? '') === $mimeType && isset($payload['body']['data'])) {
            return base64_decode(strtr((string) $payload['body']['data'], '-_', '+/')) ?: null;
        }
        foreach ($payload['parts'] ?? [] as $part) {
            $found = $this->part($part, $mimeType);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $query */
    private function get(GmailConnection $connection, string $path, array $query = []): Response
    {
        $response = $this->request($connection)->get(self::API.$path, $query);
        $this->throwIfFailed($response);

        return $response;
    }

    private function request(GmailConnection $connection): PendingRequest
    {
        // Retry only what a retry can fix: a dropped connection, Google being busy or rate limiting.
        return Http::withToken($this->accessToken($connection))->acceptJson()->timeout(20)->retry(
            2, 500,
            fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && ($e->response->serverError() || $e->response->status() === 429)),
            throw: false,
        );
    }

    private function throwIfFailed(Response $response): void
    {
        if ($response->status() === 401) {
            throw GmailException::relink('Google did not accept the Gmail permission.');
        }
        if ($response->failed()) {
            throw new GmailException('Gmail answered '.$response->status().': '.mb_substr((string) $response->json('error.message', $response->body()), 0, 200));
        }
    }

    /** A valid access token, refreshed with the stored refresh token when it is about to expire. */
    private function accessToken(GmailConnection $connection): string
    {
        if ($connection->access_token !== null && $connection->access_token_expires_at?->isAfter(now()->addMinute())) {
            return $connection->access_token;
        }

        $response = Http::asForm()->timeout(20)->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);
        if ($response->json('error') === 'invalid_grant') {
            throw GmailException::relink('The Gmail permission was withdrawn or has expired.');
        }
        if ($response->failed() || ! is_string($response->json('access_token'))) {
            throw new GmailException('Could not renew the Gmail permission ('.$response->status().').');
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'access_token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return (string) $connection->access_token;
    }
}
