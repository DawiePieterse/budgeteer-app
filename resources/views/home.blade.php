@extends('layouts.app')

@section('content')
    <nav class="period" aria-label="Budget period">
        <a href="{{ route('home', ['in' => $period->previous()->from->toDateString()]) }}" aria-label="Previous period">‹</a>
        <h1>{{ $period->label() }}</h1>
        <a href="{{ route('home', ['in' => $period->next()->from->toDateString()]) }}" aria-label="Next period">›</a>
    </nav>

    @if ($toCategorise > 0)
        <a class="notice action" href="{{ route('categorise') }}">{{ $toCategorise }} transactions to categorise ›</a>
    @endif

    @if ($budgeted > 0)
        <p class="summary-line">
            <span>Money in <strong class="in">{{ money($received) }}</strong></span>
            <span>In − spent <strong @class(['in' => $received >= $spent, 'out' => $received < $spent])>{{ money($received - $spent) }}</strong></span>
        </p>
    @else
        <section class="totals">
            <div><span class="muted small">Money in</span><strong class="in">{{ money($received) }}</strong></div>
            <div><span class="muted small">Spent</span><strong>{{ money($spent) }}</strong></div>
            <div><span class="muted small">Money in − spent</span><strong @class(['in' => $received >= $spent, 'out' => $received < $spent])>{{ money($received - $spent) }}</strong></div>
        </section>
    @endif

    @php($budgetLines = collect($spending)->filter(fn ($r) => ($r['budget'] ?? null) !== null))
    @php($otherLines = collect($spending)->filter(fn ($r) => ($r['budget'] ?? null) === null))
    @if ($budgeted > 0)
        @php($lineLink = fn ($row) => route('transactions.index', ['category' => $row['id'], 'month' => $period->from->toDateString()]))
        {{-- Lines over budget or at least 80% used show; the rest are one tap away. --}}
        @php($watch = $budgetLines->filter(fn ($r) => $r['budget'] > 0 ? $r['cents'] / $r['budget'] >= 0.8 : $r['cents'] > 0))
        @php($onTrack = $budgetLines->diffKeys($watch))
        <section class="card">
            <div class="card-head">
                <h2>Budget <a class="small" href="{{ route('budget') }}">Change ›</a></h2>
                <nav class="switch" aria-label="Show the budget as">
                    <a href="{{ route('home', ['in' => $period->from->toDateString(), 'view' => 'circles']) }}" @if ($budgetView === 'circles') aria-current="true" @endif>Circles</a>
                    <a href="{{ route('home', ['in' => $period->from->toDateString(), 'view' => 'list']) }}" @if ($budgetView === 'list') aria-current="true" @endif>List</a>
                </nav>
            </div>
            @if ($budgetView === 'circles')
                <x-budget-ring :spent="(int) $budgetLines->sum('cents')" :budget="$budgeted" />
                {{-- In budget-list order, so each line keeps its place. --}}
                <div class="circles">
                    @foreach ($budgetLines->sortBy('sort') as $row)
                        <x-budget-circle :id="'c'.$row['id']" :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" :icon="$row['icon']" :href="$lineLink($row)" />
                    @endforeach
                    @if ($otherLines->sum('cents') > 0)
                        <x-budget-circle id="other" :spent="(int) $otherLines->sum('cents')" label="Other" icon="tag" :href="route('transactions.index', ['month' => $period->from->toDateString()])" />
                    @endif
                </div>
            @else
                <x-budget-line class="total" :spent="(int) $budgetLines->sum('cents')" :budget="$budgeted" label="All budget lines" />
                @foreach ($watch as $row)
                    <x-budget-line :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" :href="$lineLink($row)" />
                @endforeach
                @if ($onTrack->isNotEmpty())
                    <details class="more-lines" @if ($watch->isEmpty()) open @endif>
                        <summary>{{ $watch->isEmpty() ? 'All' : 'Show' }} {{ $onTrack->count() }} {{ $watch->isEmpty() ? '' : 'more ' }}on track</summary>
                        @foreach ($onTrack as $row)
                            <x-budget-line :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" :href="$lineLink($row)" />
                        @endforeach
                    </details>
                @endif
            @endif
        </section>
    @endif

    @if ($recurring === [])
        <a class="notice action" href="{{ route('recurring.index') }}">Set up recurring payments (debit orders, levies…) to be told when one is late or changes ›</a>
    @else
        @php($attention = collect($recurring)->filter->needsAttention())
        @php($paid = collect($recurring)->whereIn('status', ['paid', 'paid_by_hand'])->count())
        <section class="card">
            <h2>Recurring payments <a class="small" href="{{ route('recurring.index', ['in' => $period->from->toDateString()]) }}">All ›</a></h2>
            <p class="small">{{ $paid }} of {{ count($recurring) }} paid this month @if ($attention->isEmpty()) · <span class="status ok">✓ nothing unusual</span>@endif</p>
            @foreach ($attention as $o)
                <a class="row" href="{{ route('recurring.index', ['in' => $period->from->toDateString()]) }}">
                    <span>{{ $o->payment->name }} <span class="muted small block">{{ $o->dueOn->format('j M') }}</span></span>
                    <span class="amount"><span class="status over">⚠ {{ $o->label() }}</span>
                        <span class="muted small block">@if ($o->transaction) {{ money(abs($o->transaction->amount_cents)) }}, expected {{ money($o->payment->amount_cents) }} @else {{ money($o->payment->amount_cents) }} not seen @endif</span>
                    </span>
                </a>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>{{ $budgeted > 0 ? 'Not in the budget' : 'Spending' }}@if ($budgeted === 0) <a class="small" href="{{ route('budget') }}">Set a budget ›</a>@endif</h2>
        @forelse ($otherLines as $row)
            <div class="row">
                <span>{{ $row['name'] }}</span>
                <span class="amount">{{ money($row['cents']) }}</span>
            </div>
        @empty
            <p class="muted small">@if ($budgeted > 0) Everything spent is in a budget line. @else Nothing spent in this period yet. <a href="{{ route('statements.index') }}">Add a statement</a>. @endif</p>
        @endforelse
    </section>

    @if ($income !== [])
        <section class="card">
            <h2>Money in</h2>
            @foreach ($income as $row)
                <div class="row"><span>{{ $row['name'] }}</span><span class="amount in">{{ money($row['cents']) }}</span></div>
            @endforeach
        </section>
    @endif

    @if ($projects->isNotEmpty())
        <section class="card">
            <h2>Special projects</h2>
            @foreach ($projects as $project)
                <a class="row" href="{{ route('projects.show', $project) }}"><span>{{ $project->name }}</span><span class="amount">{{ money($project->spentCents()) }} ›</span></a>
            @endforeach
        </section>
    @endif

    @if ($owedToUs->isNotEmpty())
        <section class="card">
            <h2>Owed to us <span class="muted small">{{ money($owedToUs->sum('cents')) }}</span></h2>
            @foreach ($owedToUs as $row)
                <a class="row" href="{{ route('people.show', $row['person']) }}">
                    <span><span class="owner-dot owner-{{ $row['person']->ownerColour() }}" aria-hidden="true"></span>{{ $row['person']->name }}</span>
                    <span @class(['amount', 'out' => $row['cents'] > 0])>{{ money($row['cents']) }} ›</span>
                </a>
            @endforeach
        </section>
    @endif

    @if ($accounts->isNotEmpty())
        <section class="card">
            <h2>Accounts</h2>
            @foreach ($accounts as $account)
                <div class="row">
                    <span>{{ $account->name }} <span class="muted small">{{ $account->bank->label() }} ••{{ $account->number_ending }}</span></span>
                    <span class="amount">
                        @if ($account->statement_balance_cents !== null)
                            {{ money($account->statement_balance_cents) }}
                            <span class="muted small block">on {{ $account->statement_balance_on->format('j M') }}</span>
                        @endif
                    </span>
                </div>
            @endforeach
        </section>
    @endif
@endsection
