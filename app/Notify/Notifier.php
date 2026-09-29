<?php

namespace App\Notify;

use App\Models\Household;
use App\Models\PushSubscription;
use App\Models\SentNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sends each new notice once to everyone in the household who wants that kind. Several new notices
 * of one kind at the same time go as one notification, so a phone is not flooded.
 */
class Notifier
{
    public function __construct(private NoticeFinder $finder, private PushSender $sender) {}

    /** @return int notifications sent to phones */
    public function run(Household $household): int
    {
        $notices = $this->finder->find($household);
        $this->forgetFixedGmail($household, $notices);

        $sent = SentNotification::query()->where('household_id', $household->id)->pluck('key')->flip();
        $new = array_values(array_filter($notices, fn (Notice $n) => ! isset($sent[$n->key])));
        if ($new === []) {
            return 0;
        }

        $users = User::query()->where('household_id', $household->id)->with('pushSubscriptions')->get();
        $count = 0;
        foreach (collect($new)->groupBy('kind') as $kind => $group) {
            $message = $this->message($kind, $group->all());
            DB::transaction(function () use ($household, $group) {
                foreach ($group as $notice) {
                    foreach ([$notice->key, ...$notice->alsoMarks] as $key) {
                        SentNotification::query()->firstOrCreate(
                            ['household_id' => $household->id, 'key' => $key],
                            ['title' => mb_substr($notice->title, 0, 150), 'body' => mb_substr($notice->body, 0, 500)],
                        );
                    }
                }
            });
            foreach ($users as $user) {
                if (! $user->{'notify_'.$kind}) {
                    continue;
                }
                foreach ($user->pushSubscriptions as $subscription) {
                    $count += $this->deliver($subscription, $message);
                }
            }
        }

        return $count;
    }

    /** Sends a test notification to one person's phones. */
    public function test(User $user): int
    {
        $count = 0;
        foreach ($user->pushSubscriptions as $subscription) {
            $count += $this->deliver($subscription, [
                'title' => 'Budgeteer notifications are on',
                'body' => 'This is how late payments and budget warnings will look.',
                'url' => route('home', absolute: false),
                'tag' => 'test',
            ]);
        }

        return $count;
    }

    /** @param array{title: string, body: string, url: string, tag: string} $message */
    private function deliver(PushSubscription $subscription, array $message): int
    {
        if (! $this->sender->send($subscription, $message)) {
            $subscription->delete(); // the phone turned notifications off or the app was removed

            return 0;
        }
        $subscription->update(['last_sent_at' => now()]);

        return 1;
    }

    /**
     * @param  list<Notice>  $notices
     * @return array{title: string, body: string, url: string, tag: string}
     */
    private function message(string $kind, array $notices): array
    {
        if (count($notices) === 1) {
            return ['title' => $notices[0]->title, 'body' => $notices[0]->body, 'url' => $notices[0]->url, 'tag' => $notices[0]->key];
        }

        return [
            'title' => match ($kind) {
                Notice::RECURRING => count($notices).' recurring payments need a look',
                Notice::BUDGET => count($notices).' budget warnings',
                Notice::STATEMENTS => count($notices).' statements to upload',
                default => 'Bank emails have stopped',
            },
            'body' => implode("\n", array_map(fn (Notice $n) => $n->title, $notices)),
            'url' => $kind === Notice::BUDGET ? route('home', absolute: false) : $notices[0]->url,
            'tag' => $kind.':'.now()->format('YmdHi'),
        ];
    }

    /**
     * Once Gmail works again, a later failure is news again.
     *
     * @param  list<Notice>  $notices
     */
    private function forgetFixedGmail(Household $household, array $notices): void
    {
        $broken = array_map(fn (Notice $n) => $n->key, array_filter($notices, fn (Notice $n) => $n->kind === Notice::GMAIL));
        SentNotification::query()->where('household_id', $household->id)->where('key', 'like', 'gmail:%')
            ->whereNotIn('key', $broken)->delete();
    }
}
