<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Person;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $query = Transaction::query()->with(['account', 'category', 'person', 'project'])->orderByDesc('posted_on')->orderByDesc('id');
        if ($request->filled('account')) {
            $query->where('account_id', $request->integer('account'));
        }
        if ($request->input('category') === 'none') {
            $query->whereNull('category_id')->where('is_transfer', false)->whereNull('person_id')->whereNull('project_id');
        } elseif ($request->filled('category')) {
            $query->where('category_id', $request->integer('category'));
        }
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('posted_on', [$request->date('from')->toDateString(), $request->date('to')->toDateString()]);
        }
        if ($request->filled('q')) {
            $query->where('description', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $request->string('q')).'%');
        }

        $filtered = $request->hasAny(['category', 'account', 'q', 'from']) && collect($request->only(['category', 'account', 'q', 'from']))->filter()->isNotEmpty();

        return view('transactions.index', [
            'total' => $filtered ? ['count' => (clone $query)->count(), 'cents' => (int) (clone $query)->sum('amount_cents')] : null,
            'transactions' => $query->paginate(50)->withQueryString(),
            'accounts' => Account::query()->get(),
            'category' => $request->filled('category') && $request->input('category') !== 'none' ? Category::query()->find($request->integer('category')) : null,
            'categories' => Category::query()->orderBy('kind')->orderBy('sort')->orderBy('name')->get()->groupBy(fn (Category $c) => $c->kind->value),
        ]);
    }

    public function edit(Transaction $transaction): View
    {
        return view('transactions.edit', [
            'transaction' => $transaction,
            'categories' => Category::query()->orderBy('kind')->orderBy('sort')->get()->groupBy(fn (Category $c) => $c->kind->value),
            'people' => Person::query()->orderBy('name')->get(),
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['nullable', Rule::in(Category::query()->pluck('id')->all())],
            'is_transfer' => ['boolean'],
            'person_id' => ['nullable', Rule::in(Person::query()->pluck('id')->all())],
            'project_id' => ['nullable', Rule::in(Project::query()->pluck('id')->all())],
        ]);
        $isTransfer = (bool) ($data['is_transfer'] ?? false);
        $transaction->update([
            'category_id' => $isTransfer ? null : ($data['category_id'] ?? null),
            'is_transfer' => $isTransfer,
            'person_id' => $isTransfer || $transaction->settlement()->exists() ? $transaction->person_id : ($data['person_id'] ?? null),
            'project_id' => $isTransfer ? null : ($data['project_id'] ?? null),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('transactions.index')->with('status', 'Saved.');
    }
}
