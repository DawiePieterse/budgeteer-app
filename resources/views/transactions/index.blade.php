@extends('layouts.app')

@section('title', 'Transactions · Budgeteer')

@section('content')
    <h1>Transactions</h1>
    @if ($category)
        <p class="small">{{ $category->name }}@if (request('from')) · {{ \Carbon\Carbon::parse(request('from'))->format('j M') }} – {{ \Carbon\Carbon::parse(request('to'))->format('j M Y') }}@endif · <a href="{{ route('transactions.index') }}">show all</a></p>
    @endif

    <form method="GET" class="filters">
        <label class="visually-hidden" for="q">Search</label>
        <input type="search" name="q" id="q" value="{{ request('q') }}" placeholder="Search">
        <label class="visually-hidden" for="account">Account</label>
        <select name="account" id="account" data-autosubmit>
            <option value="">All accounts</option>
            @foreach ($accounts as $account)
                <option value="{{ $account->id }}" @selected(request('account') == $account->id)>{{ $account->name }}</option>
            @endforeach
        </select>
        @foreach (['from', 'to'] as $keep)
            @if (request($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
        @endforeach
        <label class="visually-hidden" for="category">Category</label>
        <select name="category" id="category" data-autosubmit>
            <option value="">All categories</option>
            <option value="none" @selected(request('category') === 'none')>Not categorised</option>
            @foreach (['expense' => 'Budget lines', 'income' => 'Money in'] as $kind => $label)
                <optgroup label="{{ $label }}">
                    @foreach ($categories[$kind] ?? [] as $c)
                        <option value="{{ $c->id }}" @selected((string) request('category') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <button type="submit" class="secondary">Show</button>
    </form>

    @if ($total)
        <p class="summary-line"><span>{{ $total['count'] }} {{ $total['count'] === 1 ? 'transaction' : 'transactions' }}</span><span>Total <strong @class(['in' => $total['cents'] > 0])>{{ money($total['cents']) }}</strong></span></p>
    @endif

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

@push('scripts')
    <script src="{{ asset('js/autosubmit.js') }}?v=1" defer></script>
@endpush
