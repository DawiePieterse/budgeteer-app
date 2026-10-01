<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Card;
use App\Models\GmailConnection;
use App\Models\IngestedEmail;
use App\Models\Person;
use App\Models\Project;
use App\Models\RecurringPayment;
use App\Models\User;
use App\Notify\NoticeFinder;
use App\Recurring\RecurringSchedule;
use App\Services\BudgetPeriod;
use App\Services\PersonBalance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The More tab: an index of the less used screens, and one page per group of settings. */
class SettingsController extends Controller
{
    public function index(Request $request, PersonBalance $balances, NoticeFinder $notices, RecurringSchedule $schedule): View
    {
        $household = $request->user()->household;
        $today = CarbonImmutable::today();
        $owed = Person::query()->orderBy('name')->get()->map(fn (Person $p) => ['person' => $p, 'cents' => $balances->owed($p)]);
        $recurring = $schedule->occurrences(RecurringPayment::query()->where('active', true)->get(), BudgetPeriod::containing($today, $household->period_start_day));

        return view('settings.index', [
            'household' => $household,
            'statementsDue' => $notices->statementsDue($household, $today),
            'recurringAttention' => collect($recurring)->filter->needsAttention()->count(),
            'recurringCount' => count($recurring),
            'owed' => $owed,
            'projects' => Project::query()->orderBy('name')->pluck('name'),
            'accountCount' => Account::query()->count(),
            'chargedCards' => Card::query()->whereNotNull('charge_to_person_id')->count(),
            'connections' => GmailConnection::query()->get(),
            'me' => $request->user(),
            'devices' => $request->user()->pushSubscriptions()->count(),
            'users' => User::query()->where('household_id', $request->user()->household_id)->count(),
        ]);
    }

    public function household(Request $request): View
    {
        return view('settings.household', [
            'household' => $request->user()->household,
            'users' => User::query()->where('household_id', $request->user()->household_id)->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'period_start_day' => ['required', 'integer', 'between:1,28'],
            'own_account_names' => ['nullable', 'string', 'max:1000'],
        ]);

        $request->user()->household->update([
            'name' => $data['name'],
            'period_start_day' => $data['period_start_day'],
            'own_account_names' => $data['own_account_names'] ?? null,
        ]);

        return back()->with('status', 'Settings saved.');
    }

    public function accounts(): View
    {
        return view('settings.accounts', [
            'accounts' => Account::query()->orderBy('bank')->get(),
            'cards' => Card::query()->with(['account', 'chargeToPerson'])->orderBy('account_id')->orderBy('number_ending')->get(),
            'people' => Person::query()->orderBy('name')->get(),
        ]);
    }

    public function updateAccounts(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'accounts' => ['array'],
            'accounts.*' => ['required', 'string', 'max:100'],
        ]);

        foreach ($data['accounts'] ?? [] as $id => $name) {
            Account::query()->whereKey($id)->update(['name' => $name]);
        }

        return back()->with('status', 'Account names saved.');
    }

    public function gmail(): View
    {
        return view('settings.gmail', [
            'connections' => GmailConnection::query()->with('user')->get(),
            'emails' => IngestedEmail::query()->with('transaction')->latest('id')->limit(15)->get(),
        ]);
    }

    public function notifications(Request $request): View
    {
        return view('settings.notifications', [
            'me' => $request->user(),
            'devices' => $request->user()->pushSubscriptions()->latest('id')->get(),
        ]);
    }
}
