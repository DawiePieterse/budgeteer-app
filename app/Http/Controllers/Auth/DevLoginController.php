<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Development only: sign in without Google. Refused unless the app is local and dev login is on. */
class DevLoginController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        abort_unless(app()->isLocal() && config('budgeteer.dev_login'), 404);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
