<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\RecurringMark;
use App\Models\RecurringPayment;
use App\Models\Transaction;
use App\Recurring\RecurringMatcher;
use App\Recurring\RecurringSchedule;
use App\Recurring\RecurringSuggestions;
use App\Services\BudgetPeriod;
use App\Statements\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RecurringController extends Controller
{
    public function index(Request $request, RecurringSchedule $schedule, RecurringSuggestions $suggestions): View
    {
        $household = $request->user()->household;
        $date = $request->date('in') ? CarbonImmutable::parse($request->date('in')) : CarbonImmutable::today();
        $period = BudgetPeriod::containing($date, $household->period_start_day);
        $payments = RecurringPayment::query()->with('category')->orderBy('name')->get();

        return view('recurring.index', [
            'period' => $period,
            'occurrences' => $schedule->occurrences($payments->where('active', true), $period),
            'payments' => $payments,
            'suggestions' => $suggestions->for($household->id),
            'categories' => Category::query()->where('kind', 'expense')->orderBy('sort')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, RecurringMatcher $matcher): RedirectResponse
    {
        $payment = RecurringPayment::create($this->validated($request));
        $linked = $matcher->link($request->user()->household_id);

        return back()->with('status', "{$payment->name} added; {$linked} earlier ".($linked === 1 ? 'payment' : 'payments').' linked to it.');
    }

    public function update(Request $request, RecurringPayment $recurring, RecurringMatcher $matcher): RedirectResponse
    {
        $data = $this->validated($request);
        if (mb_strtoupper($data['match_text']) !== mb_strtoupper($recurring->match_text)) {
            $matcher->unlink($recurring);
        }
        $recurring->update($data);
        $matcher->link($request->user()->household_id);

        return back()->with('status', "{$recurring->name} saved.");
    }

    public function destroy(RecurringPayment $recurring, RecurringMatcher $matcher): RedirectResponse
    {
        $matcher->unlink($recurring);
        $recurring->delete();

        return back()->with('status', "{$recurring->name} is no longer tracked as a recurring payment.");
    }

    /** Skipped this time, or paid somewhere Budgeteer does not see; "clear" undoes either. */
    public function mark(Request $request, RecurringPayment $recurring): RedirectResponse
    {
        $data = $request->validate([
            'due_on' => ['required', 'date'],
            'status' => ['required', Rule::in([RecurringMark::SKIPPED, RecurringMark::PAID, 'clear'])],
        ]);
        $dueOn = CarbonImmutable::parse($data['due_on'])->toDateString();
        if ($data['status'] === 'clear') {
            $recurring->marks()->whereDate('due_on', $dueOn)->delete();
        } else {
            $recurring->marks()->updateOrCreate(['due_on' => $dueOn], ['status' => $data['status'], 'user_id' => $request->user()->id]);
        }

        return back()->with('status', 'Saved.');
    }

    /** The amount changed (for example a yearly increase): expect the new amount from now on. */
    public function useAmount(RecurringPayment $recurring, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->recurring_payment_id === $recurring->id, 404);
        $recurring->update(['amount_cents' => abs($transaction->amount_cents)]);

        return back()->with('status', "{$recurring->name} is now expected at ".Money::format(abs($transaction->amount_cents)).'.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'match_text' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', Rule::in(Category::query()->pluck('id')->all())],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'amount_varies' => ['boolean'],
            'frequency' => ['required', Rule::in([RecurringPayment::WEEKLY, RecurringPayment::MONTHLY, RecurringPayment::YEARLY])],
            'day' => ['required', 'integer', 'between:1,31'],
            'month' => ['nullable', 'required_if:frequency,yearly', 'integer', 'between:1,12'],
            'active' => ['boolean'],
        ]);
        if ($data['frequency'] === RecurringPayment::WEEKLY && $data['day'] > 7) {
            $data['day'] = 1;
        }

        return [
            'name' => trim($data['name']),
            'match_text' => trim($data['match_text']),
            'category_id' => $data['category_id'] ?? null,
            'amount_cents' => (int) round(((float) $data['amount']) * 100),
            'amount_varies' => (bool) ($data['amount_varies'] ?? false),
            'frequency' => $data['frequency'],
            'day' => (int) $data['day'],
            'month' => $data['frequency'] === RecurringPayment::YEARLY ? (int) $data['month'] : null,
            'active' => (bool) ($data['active'] ?? true),
        ];
    }
}
