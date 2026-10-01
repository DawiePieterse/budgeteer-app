@extends('layouts.app')

@section('title', 'Transactions · Budgeteer')

@section('content')
    <header class="page-head"><h1>Transactions</h1></header>

    <form method="GET" class="filters" role="search">
        @if (request('merchant'))
            <input type="hidden" name="merchant" value="{{ request('merchant') }}">
        @endif
        <label class="search">
            <x-icon name="search" :size="20" />
            <span class="visually-hidden">Search</span>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search shops, order items">
        </label>
        <div class="pills">
            <label @class(['pill-select', 'active' => $month !== null])>
                <span class="visually-hidden">Month</span>
                <select name="month" data-autosubmit>
                    <option value="">All months</option>
                    @foreach ($months as $m)
                        <option value="{{ $m->from->toDateString() }}" @selected($month?->from->equalTo($m->from))>{{ $m->label() }}</option>
                    @endforeach
                </select>
                <x-icon name="down" :size="16" />
            </label>
            <label @class(['pill-select', 'active' => request()->filled('account')])>
                <span class="visually-hidden">Account</span>
                <select name="account" data-autosubmit>
                    <option value="">All accounts</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected(request('account') == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                <x-icon name="down" :size="16" />
            </label>
            <label @class(['pill-select', 'active' => request()->filled('category')])>
                <span class="visually-hidden">Category</span>
                <select name="category" data-autosubmit>
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
                <x-icon name="down" :size="16" />
            </label>
            <button type="submit" class="sm secondary search-button" data-autosubmit-hide>Show</button>
        </div>
    </form>

    @if ($total || $owners->isNotEmpty())
        <div class="stack">
            @if ($total)
                <p class="summary-line"><span>{{ $total['count'] }} {{ $total['count'] === 1 ? 'transaction' : 'transactions' }}</span><span>Total <strong @class(['in' => $total['cents'] > 0])>{{ money($total['cents']) }}</strong></span></p>
            @endif
            @if ($owners->isNotEmpty())
                <p class="owner-key">
                    <span class="owner-chip owner-green">Ours</span>
                    @foreach ($owners as $owner)
                        <span class="owner-chip owner-{{ $owner->ownerColour() }}">{{ $owner->name }}</span>
                    @endforeach
                </p>
            @endif
        </div>
    @endif

    @forelse ($transactions->groupBy(fn ($t) => $t->posted_on->toDateString()) as $day => $rows)
        @php($date = \Carbon\CarbonImmutable::parse($day))
        <section class="section" aria-label="{{ $date->format('l j F Y') }}">
            <h2 class="day-head">
                <span>@if ($date->isToday()) Today · @elseif ($date->isYesterday()) Yesterday · @endif{{ $date->format($date->year === now()->year ? 'D j M' : 'D j M Y') }}</span>
                @if (($dayTotals[$day] ?? 0) !== 0)<span class="muted">{{ money($dayTotals[$day]) }}</span>@endif
            </h2>
            <div class="card">
                @foreach ($rows as $t)
                    @include('transactions._row', ['t' => $t])
                @endforeach
            </div>
        </section>
    @empty
        <div class="card"><p class="empty">No transactions.</p></div>
    @endforelse

    {{ $transactions->links('pagination') }}
@endsection

@push('scripts')
    <script src="{{ versioned_asset('js/autosubmit.js') }}" defer></script>
@endpush
