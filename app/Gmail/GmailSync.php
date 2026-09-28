<?php

namespace App\Gmail;

use App\Gmail\Parsers\DiscoveryEmailParser;
use App\Gmail\Parsers\EmailNotUnderstood;
use App\Gmail\Parsers\EmailParser;
use App\Gmail\Parsers\EmailToIgnore;
use App\Models\GmailConnection;
use App\Models\Household;
use App\Models\IngestedEmail;
use App\Transactions\EmailTransactions;
use App\Transactions\TransferPairer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads new bank emails from one linked Gmail account: only messages with the Budgeteer label,
 * each at most once. Work is time-boxed; whatever is left is picked up on the next run, because
 * the history position only moves on once every message up to it has been handled.
 */
class GmailSync
{
    /** How far back the first sync looks. */
    public const FIRST_SYNC = 'newer_than:30d';

    public const MAX_MESSAGES = 500;

    /** @var list<EmailParser> */
    private array $parsers;

    public function __construct(
        private GmailClient $gmail,
        private EmailTransactions $transactions,
        private TransferPairer $pairer,
        DiscoveryEmailParser $discovery,
    ) {
        $this->parsers = [$discovery];
    }

    /** @return array{added: int, matched: int, other: int, finished: bool} */
    public function sync(GmailConnection $connection, int $seconds = 40): array
    {
        $deadline = microtime(true) + $seconds;
        $counts = ['added' => 0, 'matched' => 0, 'other' => 0, 'finished' => true];

        try {
            $labelId = $connection->label_id ?? $this->gmail->labelId($connection, GmailConnection::LABEL);
            if ($labelId === null) {
                $connection->update(['status' => GmailConnection::LABEL_MISSING, 'last_error' => null, 'last_synced_at' => now()]);

                return $counts;
            }

            $since = $connection->history_id !== null ? $this->gmail->labelledSince($connection, $labelId, $connection->history_id) : null;
            if ($since === null) {
                // First sync, or the saved position is too old for Gmail to answer from.
                $historyId = $this->gmail->currentHistoryId($connection);
                $ids = array_reverse($this->gmail->messageIds($connection, $labelId, $connection->history_id === null ? self::FIRST_SYNC : 'newer_than:7d', self::MAX_MESSAGES));
            } else {
                ['ids' => $ids, 'historyId' => $historyId] = $since;
            }

            $seen = IngestedEmail::withoutGlobalScopes()->where('gmail_connection_id', $connection->id)->whereIn('gmail_message_id', $ids)->pluck('gmail_message_id')->all();
            foreach (array_diff($ids, $seen) as $id) {
                if (microtime(true) > $deadline) {
                    $counts['finished'] = false;
                    break;
                }
                $message = $this->gmail->message($connection, $id);
                if ($message === null) {
                    continue; // deleted since it was listed
                }
                $status = $this->ingest($connection, $message);
                $counts[match ($status) {
                    IngestedEmail::ADDED => 'added', IngestedEmail::MATCHED => 'matched', default => 'other'
                }]++;
            }

            $this->pairer->pair($connection->household_id);
            $update = ['label_id' => $labelId, 'status' => GmailConnection::ACTIVE, 'last_error' => null, 'last_synced_at' => now()];
            if ($counts['finished']) {
                $update['history_id'] = $historyId;
            }
            $connection->update($update);
        } catch (GmailException $e) {
            $connection->update([
                'status' => $e->needsRelink ? GmailConnection::NEEDS_RELINK : GmailConnection::ERROR,
                'last_error' => mb_substr($e->getMessage(), 0, 500),
                'last_synced_at' => now(),
            ]);
            $counts['finished'] = false;
        }

        return $counts;
    }

    /**
     * Reads every labelled email since a day that has not been read yet, for example to fill in the
     * months before Gmail was linked. The normal sync position is left alone.
     *
     * @return array{added: int, matched: int, other: int, finished: bool}
     */
    public function readSince(GmailConnection $connection, CarbonInterface $since, int $seconds = 600): array
    {
        $deadline = microtime(true) + $seconds;
        $counts = ['added' => 0, 'matched' => 0, 'other' => 0, 'finished' => true];

        try {
            $labelId = $connection->label_id ?? $this->gmail->labelId($connection, GmailConnection::LABEL);
            if ($labelId === null) {
                $connection->update(['status' => GmailConnection::LABEL_MISSING]);

                return $counts;
            }
            $ids = array_reverse($this->gmail->messageIds($connection, $labelId, 'after:'.$since->format('Y/m/d'), 2000));
            $seen = IngestedEmail::withoutGlobalScopes()->where('gmail_connection_id', $connection->id)->pluck('gmail_message_id')->all();
            foreach (array_diff($ids, $seen) as $id) {
                if (microtime(true) > $deadline) {
                    $counts['finished'] = false;
                    break;
                }
                $message = $this->gmail->message($connection, $id);
                if ($message === null) {
                    continue;
                }
                $status = $this->ingest($connection, $message);
                $counts[match ($status) {
                    IngestedEmail::ADDED => 'added', IngestedEmail::MATCHED => 'matched', default => 'other'
                }]++;
            }
            $this->pairer->pair($connection->household_id);
        } catch (GmailException $e) {
            $connection->update(['status' => $e->needsRelink ? GmailConnection::NEEDS_RELINK : GmailConnection::ERROR, 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            $counts['finished'] = false;
        }

        return $counts;
    }

    /**
     * Reads again the emails that could not be read before, for example after the app learnt a new
     * kind of notification.
     *
     * @return array{added: int, matched: int, other: int, finished: bool}
     */
    public function retryUnread(GmailConnection $connection): array
    {
        $counts = ['added' => 0, 'matched' => 0, 'other' => 0, 'finished' => true];
        $unread = IngestedEmail::withoutGlobalScopes()->where('gmail_connection_id', $connection->id)
            ->whereIn('status', [IngestedEmail::UNRECOGNISED, IngestedEmail::FAILED])->get();

        try {
            foreach ($unread as $email) {
                $message = $this->gmail->message($connection, $email->gmail_message_id);
                $email->delete();
                if ($message === null) {
                    continue;
                }
                $status = $this->ingest($connection, $message);
                $counts[match ($status) {
                    IngestedEmail::ADDED => 'added', IngestedEmail::MATCHED => 'matched', default => 'other'
                }]++;
            }
            $this->pairer->pair($connection->household_id);
        } catch (GmailException $e) {
            $connection->update(['status' => $e->needsRelink ? GmailConnection::NEEDS_RELINK : GmailConnection::ERROR, 'last_error' => mb_substr($e->getMessage(), 0, 500)]);
            $counts['finished'] = false;
        }

        return $counts;
    }

    private function ingest(GmailConnection $connection, GmailMessage $message): string
    {
        $record = [
            'household_id' => $connection->household_id,
            'gmail_connection_id' => $connection->id,
            'gmail_message_id' => $message->id,
            'sender' => mb_substr($message->senderAddress(), 0, 255),
            'subject' => mb_substr($message->subject, 0, 300),
            'received_at' => $message->receivedAt,
        ];

        $parser = collect($this->parsers)->first(fn (EmailParser $p) => $p->recognises($message));
        if ($parser === null) {
            IngestedEmail::create($record + ['status' => IngestedEmail::UNRECOGNISED, 'note' => 'Not a bank notification Budgeteer can read yet.']);

            return IngestedEmail::UNRECOGNISED;
        }

        try {
            return DB::transaction(function () use ($parser, $message, $connection, $record) {
                $parsed = $parser->parse($message);
                $keepFrom = Household::query()->whereKey($connection->household_id)->value('keep_from');
                if ($keepFrom !== null && $parsed->occurredAt->toDateString() < substr((string) $keepFrom, 0, 10)) {
                    throw new EmailToIgnore('Before the date Budgeteer keeps data from.');
                }
                [$transaction, $wasThere] = $this->transactions->record($parsed, $connection->household_id);
                $status = $wasThere ? IngestedEmail::MATCHED : IngestedEmail::ADDED;
                IngestedEmail::create($record + ['status' => $status, 'transaction_id' => $transaction->id]);

                return $status;
            });
        } catch (EmailToIgnore $e) {
            IngestedEmail::create($record + ['status' => IngestedEmail::IGNORED, 'note' => mb_substr($e->getMessage(), 0, 500)]);

            return IngestedEmail::IGNORED;
        } catch (EmailNotUnderstood $e) {
            IngestedEmail::create($record + ['status' => IngestedEmail::UNRECOGNISED, 'note' => mb_substr($e->getMessage(), 0, 500)]);

            return IngestedEmail::UNRECOGNISED;
        } catch (Throwable $e) {
            Log::error('Could not read a bank email', ['message' => $message->id, 'error' => $e->getMessage()]);
            IngestedEmail::create($record + ['status' => IngestedEmail::FAILED, 'note' => 'Could not be read; see the log.']);

            return IngestedEmail::FAILED;
        }
    }
}
