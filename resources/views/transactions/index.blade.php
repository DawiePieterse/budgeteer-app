@extends('layouts.app')

@section('title', 'Transactions · Budgeteer')

@section('content')
    <h1>Transactions</h1>

    <form method="GET" class="inline filters">
        <label class="visually-hidden" for="q">Search</label>
        <input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="Search">
        <label class="visually-hidden" for="account">Account</label>
        <select name="account" id="account">
            <option value="">All accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(request('account') == $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="secondary">Show</button>
    </form>

    <section class="card">
        @forelse ($transactions as $t)
            <a class="row" href="{{ route('transactions.edit', $t) }}">
                <span>
                    {{ $t->description }}
                    <span class="muted small block">
                        {{ $t->posted_on->format('j M Y') }} · {{ $t->account->name }} ·
                        @if ($t->is_transfer) Own accounts @elseif ($t->project) Project: {{ $t->project->name }} @elseif ($t->person) {{ $t->person->name }} @else {{ $t->category->name ?? 'Not categorised' }} @endif
                    </span>
                </span>
                <span @class(['amount', 'in' => $t->amount_cents > 0, 'muted' => $t->is_transfer])>{{ money($t->amount_cents) }}</span>
            </a>
        @empty
            <p class="muted">No transactions.</p>
        @endforelse
    </section>

    {{ $transactions->links('pagination') }}
@endsection
