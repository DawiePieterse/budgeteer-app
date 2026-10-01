@extends('layouts.app')

@section('title', 'Budget · Budgeteer')

@section('content')
    @include('partials.budget-tabs', ['current' => 'monthly'])

    @php($expenses = $categories->filter(fn ($c) => $c->kind->value === 'expense'))
    @php($incomes = $categories->filter(fn ($c) => $c->kind->value === 'income'))
    @php($budgetedLines = $expenses->filter(fn ($c) => $c->budget_cents !== null)->count())

    <section class="card summary-card" aria-label="Spending budget">
        <span class="hero-label">Spending budget</span>
        <strong class="summary-value">{{ money($total) }} <small>a month</small></strong>
        <span class="item-sub">{{ $budgetedLines }} {{ $budgetedLines === 1 ? 'line' : 'lines' }} · the budget month starts on day {{ auth()->user()->household->period_start_day }} (now {{ $period->label() }}) · <a href="{{ route('settings.household') }}">change</a></span>
    </section>

    <form method="POST" action="{{ route('budget.update') }}" class="stack" data-save-bar>
        @csrf
        @foreach (['expense' => [$expenses, 'Spending lines'], 'income' => [$incomes, 'Money in lines']] as $kind => [$lines, $heading])
            <section class="section" aria-labelledby="{{ $kind }}-h">
                <div class="section-head"><h2 id="{{ $kind }}-h">{{ $heading }}</h2><span class="muted">Rand a month</span></div>
                <div class="card">
                    @foreach ($lines as $category)
                        <div class="budget-row">
                            @if ($kind === 'expense')
                                <label class="tile line-icon icon-pick">
                                    <x-category-icon :icon="$category->iconName()" :size="22" />
                                    <span class="visually-hidden">Icon for {{ $category->name }}</span>
                                    <select name="icon[{{ $category->id }}]" data-icon-pick>
                                        <option value="" data-path="{{ \App\Support\CategoryIcons::path(\App\Support\CategoryIcons::guess($category->name)) }}">Guess from the name</option>
                                        @foreach (\App\Support\CategoryIcons::choices() as $icon => $iconLabel)
                                            <option value="{{ $icon }}" data-path="{{ \App\Support\CategoryIcons::path($icon) }}" @selected($category->icon === $icon)>{{ $iconLabel }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @else
                                <span class="tile ok"><x-icon name="money" /></span>
                            @endif
                            <span class="item-main">
                                <label class="visually-hidden" for="name-{{ $category->id }}">Name</label>
                                <input type="text" class="name-input" name="name[{{ $category->id }}]" id="name-{{ $category->id }}" value="{{ $category->name }}" maxlength="100" required>
                                <span class="item-sub">{{ $used[$category->id] ?? 0 }} {{ ($used[$category->id] ?? 0) === 1 ? 'transaction' : 'transactions' }}</span>
                            </span>
                            <label class="rand">
                                <span aria-hidden="true">R</span>
                                <span class="visually-hidden">Budget for {{ $category->name }} (R)</span>
                                <input type="number" name="budget[{{ $category->id }}]" step="0.01" min="0" inputmode="decimal" placeholder="None" value="{{ $category->budget_cents !== null ? number_format($category->budget_cents / 100, 2, '.', '') : '' }}">
                            </label>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <details class="card disclosure">
            <summary class="item">
                <span class="tile accent"><x-icon name="plus" /></span>
                <span class="item-main"><span class="item-title">Add a line</span><span class="item-sub">A new spending or money in line</span></span>
                <x-icon name="forward" class="chev" :size="20" />
            </summary>
            <div class="disclosure-body">
                <div class="pair wide-first">
                    <div class="field"><label for="new_name">Name</label><input type="text" name="new_name" id="new_name" maxlength="100"></div>
                    <div class="field"><label for="new_budget">Rand a month</label><input type="number" class="money" name="new_budget" id="new_budget" step="0.01" min="0" inputmode="decimal"></div>
                </div>
                <fieldset class="actions">
                    <legend>Kind</legend>
                    <label class="check"><input type="radio" name="new_kind" value="expense" checked> Spending</label>
                    <label class="check"><input type="radio" name="new_kind" value="income"> Money in</label>
                </fieldset>
            </div>
        </details>

        <div class="save-bar">
            <span class="grow">Changes to names, amounts and icons</span>
            <button type="reset" class="ghost">Undo</button>
            <button type="submit">Save budget</button>
        </div>
    </form>

    <section class="section" aria-labelledby="tools-h">
        <div class="section-head"><h2 id="tools-h">Tools</h2></div>
        <div class="card">
            <details class="disclosure">
                <summary class="item">
                    <span class="tile"><x-icon name="paste" /></span>
                    <span class="item-main"><span class="item-title">Paste lines from a spreadsheet</span><span class="item-sub">One per line, the amount last</span></span>
                    <x-icon name="forward" class="chev" :size="20" />
                </summary>
                <form method="POST" action="{{ route('budget.paste') }}" class="disclosure-body">
                    @csrf
                    <p class="hint">Copy the lines from your spreadsheet: one item per line, the monthly amount at the end. Each line becomes a category with that budget; an existing category with the same name gets the amount.</p>
                    <label class="visually-hidden" for="list">Budget lines</label>
                    <textarea name="list" id="list" rows="8" placeholder="Everyday food items &amp; household basics    10000&#10;Petrol &amp; tolls    4000"></textarea>
                    <button type="submit" class="secondary">Read these lines</button>
                </form>
            </details>
            <details class="disclosure">
                <summary class="item">
                    <span class="tile"><x-icon name="merge" /></span>
                    <span class="item-main"><span class="item-title">Move one line into another</span><span class="item-sub">Its transactions and budget move too</span></span>
                    <x-icon name="forward" class="chev" :size="20" />
                </summary>
                <form method="POST" action="{{ route('budget.merge') }}" class="disclosure-body">
                    @csrf
                    <p class="hint">Everything in the first, and what Budgeteer remembers for it, moves to the second, and the two budgets are added together; the first is removed. Rename the second above if it needs a broader name.</p>
                    <div class="field">
                        <label for="from">Move</label>
                        <select name="from" id="from" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }} ({{ $used[$category->id] ?? 0 }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="into">into</label>
                        <select name="into" id="into" required>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="secondary">Move</button>
                </form>
            </details>
            <details class="disclosure">
                <summary class="item">
                    <span class="tile"><x-icon name="trash" /></span>
                    <span class="item-main"><span class="item-title">Remove unused lines</span><span class="item-sub">No budget and nothing in them</span></span>
                    <x-icon name="forward" class="chev" :size="20" />
                </summary>
                <form method="POST" action="{{ route('budget.tidy') }}" class="disclosure-body">
                    @csrf
                    <button type="submit" class="secondary">Remove unused categories</button>
                </form>
            </details>
        </div>

        <details class="card danger disclosure">
            <summary class="item">
                <span class="tile danger"><x-icon name="restart" /></span>
                <span class="item-main"><span class="item-title out">Start again from my budget</span><span class="item-sub">Replaces every spending line and forgets which shop goes where</span></span>
                <x-icon name="forward" class="chev" :size="20" />
            </summary>
            <form method="POST" action="{{ route('budget.reset') }}" class="disclosure-body">
                @csrf
                <p class="hint">Replaces <strong>all spending categories</strong> with these lines and forgets which shop goes where, so every transaction is categorised again. Transactions, money-in categories, projects and what people owe are kept.</p>
                <label class="visually-hidden" for="reset-list">Budget lines</label>
                <textarea name="list" id="reset-list" rows="8" required placeholder="Everyday food items &amp; household basics    10000&#10;Petrol &amp; tolls    4000"></textarea>
                <label class="check"><input type="checkbox" name="confirm" value="1" required> Yes, replace my spending categories</label>
                <button type="submit" class="outline">Replace categories</button>
            </form>
        </details>
    </section>
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/budget.js') }}" defer></script>
@endpush
