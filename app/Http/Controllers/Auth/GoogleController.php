<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')->with('error', 'The sign-in took too long. Please try again.');
        }

        // Only people on the household's allowlist have a user row, so an unknown address is refused.
        $email = strtolower((string) $google->getEmail());
        $user = User::where('email', $email)->first();
        if ($user === null || ($user->google_id !== null && $user->google_id !== $google->getId())) {
            return redirect()->route('login')->with('error', "{$email} is not allowed to use Budgeteer.");
        }

        $user->update([
            'google_id' => $google->getId(),
            'name' => $google->getName() ?: $user->name,
            'avatar_url' => $google->getAvatar(),
            'last_signed_in_at' => now(),
        ]);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
