<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Person;
use App\Models\Settlement;
use App\Models\Transaction;
use App\Services\PersonBalance;
use App\Statements\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonController extends Controller
{
    public function show(Person $person, PersonBalance $balances): View
    {
        $charges = $balances->charges($person)->with(['card', 'category'])->orderByDesc('posted_on')->orderByDesc('id')->get();
        $monthStart = now()->startOfMonth()->toDateString();
        $categories = Category::query()->pluck('name', 'id');
        $thisMonth = $charges->filter(fn (Transaction $t) => $t->posted_on->toDateString() >= $monthStart)
            ->groupBy(fn (Transaction $t) => $categories[$t->category_id] ?? 'Not categorised')
            ->map(fn ($group) => -(int) $group->sum('amount_cents'))
            ->sortDesc();

        return view('people.show', [
            'person' => $person,
            'owed' => $balances->owed($person),
            'charges' => $charges,
            'thisMonth' => $thisMonth,
            'settlements' => $person->settlements()->with(['transaction', 'user'])->latest('received_on')->latest('id')->get(),
            'possible' => $balances->possibleRepayments($person),
            'whatsApp' => $this->whatsApp($person, $balances->owed($person)),
        ]);
    }

    public function update(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 +()-]+$/'],
            'opening_balance' => ['required', 'numeric', 'between:-10000000,10000000'],
            'opening_balance_on' => ['required', 'date'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $person->update([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'opening_balance_cents' => (int) round(((float) $data['opening_balance']) * 100),
            'opening_balance_on' => $data['opening_balance_on'],
            'payment_reference' => $data['payment_reference'] ?? null,
        ]);

        return back()->with('status', 'Saved.');
    }

    /** A repayment received in cash or anywhere Budgeteer does not see. */
    public function storeSettlement(Request $request, Person $person): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000'],
            'received_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $person->settlements()->create([
            'household_id' => $person->household_id,
            'amount_cents' => (int) round(((float) $data['amount']) * 100),
            'received_on' => $data['received_on'],
            'note' => $data['note'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Repayment recorded.');
    }

    /** A payment into our account that is this person paying back: not household income. */
    public function settleFromTransaction(Request $request, Person $person, Transaction $transaction): RedirectResponse
    {
        abort_if($transaction->amount_cents <= 0 || $transaction->settlement()->exists(), 422);

        $transaction->update(['person_id' => $person->id, 'category_id' => null, 'updated_by' => $request->user()->id]);
        $person->settlements()->create([
            'household_id' => $person->household_id,
            'amount_cents' => $transaction->amount_cents,
            'received_on' => $transaction->posted_on,
            'transaction_id' => $transaction->id,
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', Money::format($transaction->amount_cents).' recorded as paid back.');
    }

    public function destroySettlement(Person $person, Settlement $settlement): RedirectResponse
    {
        abort_unless($settlement->person_id === $person->id, 404);
        $settlement->transaction?->update(['person_id' => null]);
        $settlement->delete();

        return back()->with('status', 'Repayment removed.');
    }

    private function whatsApp(Person $person, int $owed): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $person->phone) ?? '';
        if ($digits === '' || $owed <= 0) {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '27'.substr($digits, 1); // South African number
        }
        $first = explode(' ', $person->name)[0];

        return 'https://wa.me/'.$digits.'?text='.rawurlencode("Hi {$first}, your card balance with us is ".Money::format($owed).'. Thanks!');
    }
}
