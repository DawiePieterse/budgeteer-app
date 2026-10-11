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
        {{-- Spending lines under their groups, in the groups' order, and those without a group last; with no groups yet, one list. --}}
        @php($sections = $groups->isEmpty() ? [[null, $expenses]] : [...$groups->map(fn ($g) => [$g, $expenses->where('category_group_id', $g->id)])->all(), [null, $expenses->whereNull('category_group_id')]])
        @foreach (['expense' => [$sections, 'Spending lines'], 'income' => [[[null, $incomes]], 'Money in lines']] as $kind => [$parts, $heading])
            <section class="section" aria-labelledby="{{ $kind }}-h">
                <div class="section-head"><h2 id="{{ $kind }}-h">{{ $heading }}</h2><span class="muted">Rand a month</span></div>
                <div class="card">
                    @foreach ($parts as [$group, $lines])
                        @if ($kind === 'expense' && $groups->isNotEmpty() && ($group !== null || $lines->isNotEmpty()))
                            <div @class(['budget-group-head', 'group-colour-'.$group?->colour => $group !== null])>
                                <span @class(['group-dot', 'none' => $group === null]) aria-hidden="true"></span>
                                <span class="grow">{{ $group?->name ?? 'No group' }}</span>
                                <span class="muted">{{ money((int) $lines->sum('budget_cents')) }}</span>
                            </div>
                        @endif
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
                                <span class="item-sub line-meta">
                                    @if ($kind === 'expense' && $groups->isNotEmpty())
                                        <label class="visually-hidden" for="group-{{ $category->id }}">Group for {{ $category->name }}</label>
                                        <select class="group-select" name="group[{{ $category->id }}]" id="group-{{ $category->id }}">
                                            <option value="">No group</option>
                                            @foreach ($groups as $g)
                                                <option value="{{ $g->id }}" @selected($category->category_group_id === $g->id)>{{ $g->name }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    <span>{{ $used[$category->id] ?? 0 }} {{ ($used[$category->id] ?? 0) === 1 ? 'transaction' : 'transactions' }}</span>
                                </span>
                            </span>
                            <label class="rand">
                                <span aria-hidden="true">R</span>
                                <span class="visually-hidden">Budget for {{ $category->name }} (R)</span>
                                <input type="number" name="budget[{{ $category->id }}]" step="0.01" min="0" inputmode="decimal" placeholder="None" value="{{ $category->budget_cents !== null ? number_format($category->budget_cents / 100, 2, '.', '') : '' }}">
                            </label>
                        </div>
                    @endforeach
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
            <span class="grow">Changes to names, amounts, icons and groups</span>
            <button type="reset" class="ghost">Undo</button>
            <button type="submit">Save budget</button>
        </div>
    </form>

    <section class="section" aria-labelledby="groups-h">
        <div class="section-head"><h2 id="groups-h">Groups</h2></div>
        <form method="POST" action="{{ route('budget.groups') }}" class="card groups-card">
            @csrf
            {{-- Enter in a name saves, rather than pressing the first row's Remove. --}}
            <button type="submit" class="visually-hidden" tabindex="-1" aria-hidden="true">Save groups</button>
            <p class="hint">Put spending lines together, like the sections of your budget spreadsheet. On a computer the Overview shows spending per group. Choose each line's group under its name above.</p>
            @foreach ($groups as $group)
                <div @class(['group-row', 'group-colour-'.$group->colour])>
                    <label class="group-swatch">
                        <span class="group-dot" aria-hidden="true"></span>
                        <span class="visually-hidden">Colour for {{ $group->name }}</span>
                        <select name="group_colour[{{ $group->id }}]" data-group-colour>
                            @foreach (\App\Support\GroupColours::CHOICES as $colour => $colourLabel)
                                <option value="{{ $colour }}" @selected($group->colour === $colour)>{{ $colourLabel }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="visually-hidden" for="group-name-{{ $group->id }}">Name</label>
                    <input type="text" class="name-input" name="group_name[{{ $group->id }}]" id="group-name-{{ $group->id }}" value="{{ old('group_name.'.$group->id, $group->name) }}" maxlength="60" required>
                    @php($count = $expenses->where('category_group_id', $group->id)->count())
                    <span class="item-sub group-count">{{ $count }} {{ $count === 1 ? 'line' : 'lines' }}</span>
                    @if (! $loop->first)
                        <button type="submit" class="icon-button" name="action" value="up:{{ $group->id }}" aria-label="Move {{ $group->name }} up"><x-icon name="up" :size="20" /></button>
                    @else
                        <span class="icon-button-space" aria-hidden="true"></span>
                    @endif
                    <button type="submit" class="icon-button" name="action" value="remove:{{ $group->id }}" aria-label="Remove {{ $group->name }}"><x-icon name="trash" :size="20" /></button>
                </div>
            @endforeach
            <div class="pair wide-first group-add">
                <div class="field"><label for="new_group">{{ $groups->isEmpty() ? 'First group' : 'Add a group' }}</label><input type="text" name="new_group" id="new_group" maxlength="60" value="{{ old('new_group') }}" placeholder="For example Kids"></div>
                <button type="submit" class="secondary">{{ $groups->isEmpty() ? 'Add' : 'Save groups' }}</button>
            </div>
        </form>
    </section>

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
                    <p class="hint">Copy the lines from your spreadsheet: one item per line, the monthly amount at the end. Each line becomes a category with that budget; an existing category with the same name gets the amount. A line with a name and no amount, like a section heading, puts the lines under it in a group of that name.</p>
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
