@extends('layouts.app')

@section('title', 'Budget · Budgeteer')

@section('content')
    <h1>Budget</h1>
    <p class="muted small">Amounts are per budget month ({{ $period->label() }} now). Spending over them shows on the home screen.</p>

    <form method="POST" action="{{ route('budget.update') }}">
        @csrf
        @foreach (['expense' => 'Spending', 'income' => 'Money in'] as $kind => $label)
            <section class="card">
                <h2>{{ $label }}@if ($kind === 'expense') <span class="muted small">· {{ money($total) }} a month</span>@endif</h2>
                @foreach ($categories->filter(fn ($c) => $c->kind->value === $kind) as $category)
                    <div class="pair budget-row">
                        <span>
                            <label class="visually-hidden" for="name-{{ $category->id }}">Name</label>
                            <input type="text" name="name[{{ $category->id }}]" id="name-{{ $category->id }}" value="{{ $category->name }}" maxlength="100" required>
                            <span class="muted small">{{ $used[$category->id] ?? 0 }} transactions</span>
                        </span>
                        <span>
                            <label class="visually-hidden" for="budget-{{ $category->id }}">Budget for {{ $category->name }} (R)</label>
                            <input type="number" name="budget[{{ $category->id }}]" id="budget-{{ $category->id }}" step="0.01" min="0" placeholder="No budget" value="{{ $category->budget_cents !== null ? number_format($category->budget_cents / 100, 2, '.', '') : '' }}">
                        </span>
                    </div>
                @endforeach
            </section>
        @endforeach

        <section class="card">
            <h2>Add a category</h2>
            <div class="pair">
                <span><label for="new_name">Name</label><input type="text" name="new_name" id="new_name" maxlength="100"></span>
                <span><label for="new_budget">Budget (R)</label><input type="number" name="new_budget" id="new_budget" step="0.01" min="0"></span>
            </div>
            <label for="new_kind">Kind</label>
            <select name="new_kind" id="new_kind">
                <option value="expense">Spending</option>
                <option value="income">Money in</option>
            </select>
        </section>

        <button type="submit">Save budget</button>
    </form>

    <section class="card spaced">
        <h2>Special projects</h2>
        <p class="muted small">Spending on a project (for example a car rebuild) is tracked on its own page and kept out of the monthly budget.</p>
        @foreach ($projects as $project)
            <a class="row" href="{{ route('projects.show', $project) }}"><span>{{ $project->name }}</span><span class="amount">{{ money($project->spentCents()) }} ›</span></a>
        @endforeach
        <form method="POST" action="{{ route('projects.store') }}">
            @csrf
            <div class="pair">
                <span><label for="project_name">New project</label><input type="text" name="name" id="project_name" maxlength="100" required></span>
                <span><label for="project_budget">Total budget (R)</label><input type="number" name="budget" id="project_budget" step="0.01" min="0" placeholder="Optional"></span>
            </div>
            <button type="submit" class="secondary">Add project</button>
        </form>
    </section>

    <form method="POST" action="{{ route('budget.paste') }}" class="card">
        @csrf
        <h2>Paste a budget</h2>
        <p class="muted small">Copy the lines from your spreadsheet: one item per line, the monthly amount at the end. Each line becomes a category with that budget; an existing category with the same name gets the amount.</p>
        <label class="visually-hidden" for="list">Budget lines</label>
        <textarea name="list" id="list" rows="8" placeholder="Everyday food items &amp; household basics    10000&#10;Petrol &amp; tolls    4000"></textarea>
        <button type="submit" class="secondary">Read these lines</button>
    </form>

    <form method="POST" action="{{ route('budget.merge') }}" class="card">
        @csrf
        <h2>Move one category into another</h2>
        <p class="muted small">Everything in the first, and what Budgeteer remembers for it, moves to the second; the first is removed.</p>
        <label for="from">Move</label>
        <select name="from" id="from" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }} ({{ $used[$category->id] ?? 0 }})</option>
            @endforeach
        </select>
        <label for="into">into</label>
        <select name="into" id="into" required>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}">{{ $category->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="secondary">Move</button>
    </form>

    <form method="POST" action="{{ route('budget.reset') }}" class="card">
        @csrf
        <h2>Start again from my budget</h2>
        <p class="muted small">Replaces <strong>all spending categories</strong> with these lines and forgets which shop goes where, so every transaction is categorised again. Transactions, money-in categories, projects and what people owe are kept.</p>
        <label class="visually-hidden" for="reset-list">Budget lines</label>
        <textarea name="list" id="reset-list" rows="8" required placeholder="Everyday food items &amp; household basics    10000&#10;Petrol &amp; tolls    4000"></textarea>
        <label class="check"><input type="checkbox" name="confirm" value="1" required> Yes, replace my spending categories</label>
        <button type="submit" class="secondary">Replace categories</button>
    </form>

    <form method="POST" action="{{ route('budget.tidy') }}" class="card">
        @csrf
        <h2>Tidy up</h2>
        <p class="muted small">Removes categories with no budget and nothing in them.</p>
        <button type="submit" class="secondary">Remove unused categories</button>
    </form>
@endsection
