<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings', [
            'household' => $request->user()->household,
            'accounts' => Account::query()->orderBy('bank')->get(),
            'users' => User::query()->where('household_id', $request->user()->household_id)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'period_start_day' => ['required', 'integer', 'between:1,28'],
            'own_account_names' => ['nullable', 'string', 'max:1000'],
            'accounts' => ['array'],
            'accounts.*' => ['required', 'string', 'max:100'],
        ]);

        $request->user()->household->update([
            'name' => $data['name'],
            'period_start_day' => $data['period_start_day'],
            'own_account_names' => $data['own_account_names'] ?? null,
        ]);
        foreach ($data['accounts'] ?? [] as $id => $name) {
            Account::query()->whereKey($id)->update(['name' => $name]);
        }

        return back()->with('status', 'Settings saved.');
    }
}
