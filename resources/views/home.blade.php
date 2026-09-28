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
        <div><span class="muted small">Money in</span><strong class="in">{{ money($received) }}</strong></div>
        <div><span class="muted small">Spent</span><strong>{{ money($spent) }}</strong></div>
        <div><span class="muted small">Difference</span><strong @class(['in' => $received >= $spent, 'out' => $received < $spent])>{{ money($received - $spent) }}</strong></div>
    </section>

    <section class="card">
        <h2>Spending</h2>
        @forelse ($spending as $row)
            <div class="row">
                <span>{{ $row['name'] }}</span>
                <span class="amount">{{ money($row['cents']) }}</span>
                <progress max="100" value="{{ $spent > 0 ? max(1, round($row['cents'] / $spent * 100)) : 0 }}" aria-hidden="true"></progress>
            </div>
        @empty
            <p class="muted">Nothing spent in this period yet. <a href="{{ route('statements.index') }}">Add a statement</a>.</p>
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
