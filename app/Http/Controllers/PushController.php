<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Notify\Notifier;
use App\Notify\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    /** Saves this phone or browser, called by push.js after the person allows notifications. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:100'],
            'device' => ['nullable', 'string', 'max:100'],
        ]);

        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hash($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'device' => $data['device'] ?? null,
            ],
        );

        return response()->json(['ok' => true]);
    }

    /** Forgets this phone, called by push.js when notifications are turned off on it. */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hash($data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    /** Forgets one of the person's phones from the Settings list. */
    public function remove(Request $request, PushSubscription $subscription): RedirectResponse
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $subscription->delete();

        return back()->with('status', 'That phone will no longer get notifications.');
    }

    public function preferences(Request $request): RedirectResponse
    {
        $request->user()->update([
            'notify_recurring' => $request->boolean('notify_recurring'),
            'notify_budget' => $request->boolean('notify_budget'),
            'notify_gmail' => $request->boolean('notify_gmail'),
        ]);

        return back()->with('status', 'Notification choices saved.');
    }

    public function test(Request $request, Notifier $notifier, PushSender $sender): RedirectResponse
    {
        if (! $sender->configured()) {
            return back()->with('error', 'Phone notifications are not set up on the server yet.');
        }
        $count = $notifier->test($request->user()->load('pushSubscriptions'));

        return back()->with($count > 0 ? 'status' : 'error', $count > 0
            ? "Test sent to {$count} ".str('phone')->plural($count).'.'
            : 'No phone got the test. Turn notifications on again on this phone.');
    }
}
