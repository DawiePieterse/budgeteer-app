<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Project;
use App\Models\Transaction;
use App\Services\BudgetPeriod;
use App\Services\PersonBalance;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    /** Choice value for charging a purchase to someone not in the list yet. */
    public const NEW_PERSON = 'new';

    public function index(Request $request): View
    {
        $query = Transaction::query()->with(['account', 'category', 'person', 'project', 'order.items'])->orderByDesc('posted_on')->orderByDesc('id');
        if ($request->filled('account')) {
            $query->where('account_id', $request->integer('account'));
        }
        if ($request->input('category') === 'none') {
            $query->whereNull('category_id')->where('is_transfer', false)->whereNull('person_id')->whereNull('project_id');
        } elseif ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        // A budget month, given by its first day; the home screen links here with one.
        $household = $request->user()->household;
        $months = $this->months($household->period_start_day);
        $month = $request->filled('month') ? BudgetPeriod::containing(CarbonImmutable::parse($request->string('month')), $household->period_start_day) : null;
        if ($month !== null) {
            $query->whereBetween('posted_on', [$month->from->toDateString(), $month->to->toDateString()]);
        } elseif ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('posted_on', [$request->date('from')->toDateString(), $request->date('to')->toDateString()]);
        }
        if ($request->filled('merchant')) {
            $query->where('merchant_key', $request->string('merchant'));
        }
        if ($request->filled('q')) {
            // The description, or an item in the online order the payment was for.
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')).'%';
            $query->where(fn ($q) => $q->where('description', 'like', $like)
                ->orWhereHas('order.items', fn ($items) => $items->where('name', 'like', $like)));
        }

        $filtered = collect($request->only(['category', 'account', 'q', 'from', 'month', 'merchant']))->filter()->isNotEmpty();
        $transactions = (clone $query)->paginate(50)->withQueryString();
        // Each day's total, with the same filters, over the whole day even when it runs onto the next page.
        // Money moved between our own accounts is left out: it is neither spent nor received.
        $days = $transactions->getCollection()->map(fn (Transaction $t) => $t->posted_on->toDateString())->unique()->values();
        $dayTotals = $days->isEmpty() ? collect() : (clone $query)->reorder()->setEagerLoads([])
            ->where('is_transfer', false)->whereIn('posted_on', $days->all())
            ->groupBy('posted_on')->selectRaw('posted_on, sum(amount_cents) as cents')->pluck('cents', 'posted_on')
            ->mapWithKeys(fn ($cents, $day) => [CarbonImmutable::parse($day)->toDateString() => (int) $cents]);

        return view('transactions.index', [
            'total' => $filtered ? ['count' => (clone $query)->count(), 'cents' => (int) (clone $query)->sum('amount_cents')] : null,
            'transactions' => $transactions,
            'dayTotals' => $dayTotals,
            'owners' => Person::query()->orderBy('name')->get()->concat(Project::query()->orderBy('name')->get()),
            'accounts' => Account::query()->get(),
            'months' => $months,
            'month' => $month,
            'category' => $request->filled('category') && $request->input('category') !== 'none' ? Category::query()->find($request->integer('category')) : null,
            'categories' => Category::query()->orderBy('kind')->orderBy('sort')->orderBy('name')->get()->groupBy(fn (Category $c) => $c->kind->value),
        ]);
    }

    /**
     * Budget months from the first transaction to now, newest first.
     *
     * @return list<BudgetPeriod>
     */
    private function months(int $startDay): array
    {
        $first = Transaction::query()->min('posted_on');
        if ($first === null) {
            return [];
        }
        $months = [];
        $period = BudgetPeriod::containing(CarbonImmutable::today(), $startDay);
        $stop = BudgetPeriod::containing(CarbonImmutable::parse($first), $startDay)->from;
        while ($period->from->greaterThanOrEqualTo($stop) && count($months) < 60) {
            $months[] = $period;
            $period = $period->previous();
        }

        return $months;
    }

    public function edit(Transaction $transaction): View
    {
        $transaction->load('order.items');
        $people = Person::query()->orderBy('name')->get();
        // An order delivered to someone who pays us back, while the payment still counts as ours.
        $deliverTo = mb_strtolower((string) $transaction->order?->deliver_to);
        $deliveredToSomeone = $deliverTo === '' || $transaction->person_id !== null ? null
            : $people->first(fn (Person $p) => str_starts_with($deliverTo, mb_strtolower(explode(' ', trim($p->name))[0]).' ') || $deliverTo === mb_strtolower(trim($p->name)));

        return view('transactions.edit', [
            'transaction' => $transaction,
            'deliveredToSomeone' => $deliveredToSomeone,
            'categories' => Category::query()->orderBy('kind')->orderBy('sort')->get()->groupBy(fn (Category $c) => $c->kind->value),
            'people' => $people,
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', Rule::in(Category::query()->pluck('id')->all())],
            'is_transfer' => ['boolean'],
            'person_id' => ['nullable', Rule::in([self::NEW_PERSON, ...Person::query()->pluck('id')->map(fn ($id) => (string) $id)->all()])],
            'new_person' => ['required_if:person_id,'.self::NEW_PERSON, 'nullable', 'string', 'max:100'],
            'project_id' => ['nullable', Rule::in(Project::query()->pluck('id')->all())],
        ], ['new_person.required_if' => 'Type the name of the person who will pay you back.']);
        $isTransfer = (bool) ($data['is_transfer'] ?? false);

        $person = null;
        if (! $isTransfer && ! $transaction->settlement()->exists()) {
            $person = match ($data['person_id'] ?? null) {
                null, '' => null,
                // Someone who owed nothing before: every purchase charged to them counts.
                self::NEW_PERSON => Person::create(['name' => trim((string) $data['new_person']), 'opening_balance_cents' => 0, 'opening_balance_on' => null]),
                default => Person::query()->findOrFail((int) $data['person_id']),
            };
        }

        $transaction->update([
            'category_id' => $isTransfer ? null : ($data['category_id'] ?? null),
            'is_transfer' => $isTransfer,
            'person_id' => $isTransfer || $transaction->settlement()->exists() ? $transaction->person_id : $person?->id,
            'project_id' => $isTransfer || $person !== null ? null : ($data['project_id'] ?? null),
            'updated_by' => $request->user()->id,
        ]);

        $status = 'Saved.';
        if ($person !== null) {
            $status = $person->opening_balance_on !== null && $transaction->posted_on->lessThanOrEqualTo($person->opening_balance_on)
                ? "Charged to {$person->name}, but it is from before {$person->opening_balance_on->format('j M Y')}, so it is already in what they owed then."
                : "Charged to {$person->name}: out of the budget, and {$person->name} now owes ".money(app(PersonBalance::class)->owed($person)).'.';
        }

        return redirect()->route('transactions.index')->with('status', $status);
    }
}
