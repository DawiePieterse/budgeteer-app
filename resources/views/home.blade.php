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

    <section class="totals">
        @if ($budgeted > 0)
            <div><span class="muted small">Spent of budget</span><strong @class(['out' => $spent > $budgeted])>{{ money($spent) }}</strong><span class="muted small">of {{ money($budgeted) }}</span></div>
            <div><span class="muted small">{{ $spent > $budgeted ? 'Over budget' : 'Left to spend' }}</span><strong @class(['in' => $spent <= $budgeted, 'out' => $spent > $budgeted])>{{ money(abs($budgeted - $spent)) }}</strong></div>
        @else
            <div><span class="muted small">Money in</span><strong class="in">{{ money($received) }}</strong></div>
            <div><span class="muted small">Spent</span><strong>{{ money($spent) }}</strong></div>
        @endif
        <div><span class="muted small">Money in − spent</span><strong @class(['in' => $received >= $spent, 'out' => $received < $spent])>{{ money($received - $spent) }}</strong><span class="muted small">{{ money($received) }} in</span></div>
    </section>

    @if ($recurring !== [])
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

    @php($budgetLines = collect($spending)->filter(fn ($r) => ($r['budget'] ?? null) !== null))
    @php($otherLines = collect($spending)->filter(fn ($r) => ($r['budget'] ?? null) === null))
    @if ($budgeted > 0)
        <section class="card">
            <h2>Budget <a class="small" href="{{ route('budget') }}">Change ›</a></h2>
            <x-budget-bar class="total" :spent="(int) $budgetLines->sum('cents')" :budget="$budgeted" label="All budget lines" />
            @foreach ($budgetLines as $row)
                <x-budget-bar :spent="$row['cents']" :budget="$row['budget']" :label="$row['name']" />
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
            <h2>Owed to us</h2>
            @foreach ($owedToUs as $row)
                <a class="row" href="{{ route('people.show', $row['person']) }}">
                    <span>{{ $row['person']->name }}</span>
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
