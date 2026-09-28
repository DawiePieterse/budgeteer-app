<?php

namespace App\Http\Controllers;

use App\Gmail\GmailClient;
use App\Gmail\GmailSync;
use App\Models\GmailConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/** Linking a Gmail account with read-only access, so bank emails can be read. */
class GmailController extends Controller
{
    public const SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    public function link(): SymfonyRedirect
    {
        return $this->google()
            ->scopes(['openid', 'email', self::SCOPE])
            // offline + consent: Google returns a refresh token, so reading can continue without anyone signed in
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback(Request $request, GmailSync $sync): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('settings')->with('error', 'Gmail was not linked.');
        }
        try {
            $google = $this->google()->user();
        } catch (InvalidStateException) {
            return redirect()->route('settings')->with('error', 'Linking took too long. Please try again.');
        }

        if (! in_array(self::SCOPE, $google->approvedScopes ?? [], true)) {
            return redirect()->route('settings')->with('error', 'Budgeteer needs the tick next to "View your email messages and settings" to read bank emails. Please link again and tick it.');
        }
        if (! is_string($google->refreshToken) || $google->refreshToken === '') {
            return redirect()->route('settings')->with('error', 'Google did not give lasting access. Please remove Budgeteer under your Google account\'s third-party access, then link again.');
        }

        $connection = GmailConnection::updateOrCreate(
            ['household_id' => $request->user()->household_id, 'email' => strtolower((string) $google->getEmail())],
            [
                'user_id' => $request->user()->id,
                'refresh_token' => $google->refreshToken,
                'access_token' => $google->token,
                'access_token_expires_at' => now()->addSeconds((int) ($google->expiresIn ?? 3600)),
                'status' => GmailConnection::ACTIVE,
                'last_error' => null,
                'label_id' => null,
            ],
        );

        $result = $sync->sync($connection, 20);

        return redirect()->route('settings')->with('status', "Gmail {$connection->email} is linked. ".$this->summary($result));
    }

    public function sync(GmailConnection $connection, GmailSync $sync): RedirectResponse
    {
        $result = $sync->sync($connection, 20);

        return back()->with('status', $this->summary($result));
    }

    public function destroy(GmailConnection $connection, GmailClient $gmail): RedirectResponse
    {
        $gmail->revoke($connection);
        $connection->delete();

        return back()->with('status', "Gmail {$connection->email} is unlinked. Transactions already read stay.");
    }

    /** @param array{added: int, matched: int, other: int, finished: bool} $result */
    private function summary(array $result): string
    {
        return sprintf(
            'Read %d new %s, %d already from statements%s.',
            $result['added'], $result['added'] === 1 ? 'transaction' : 'transactions', $result['matched'],
            $result['finished'] ? '' : '; the rest follow within a few minutes',
        );
    }

    /** @return GoogleProvider */
    private function google()
    {
        return Socialite::driver('google')->redirectUrl(route('gmail.callback'));
    }
}
