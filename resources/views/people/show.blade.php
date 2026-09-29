@extends('layouts.app')

@section('title', $person->name.' · Budgeteer')

@section('content')
    <h1>{{ $person->name }}</h1>

    <section class="card">
        <div class="row">
            @if ($owed === 0)
                <span><strong>All square</strong><span class="muted small block">{{ $person->name }} owes you nothing.</span></span>
            @else
                <span><strong>{{ $owed > 0 ? 'Owes you' : 'You owe '.$person->name }}</strong><span class="muted small block">@if ($person->opening_balance_on) since {{ $person->opening_balance_on->format('j M Y') }}, @endif less repayments</span></span>
            @endif
            <strong @class(['amount', 'out' => $owed > 0, 'in' => $owed < 0])>{{ money(abs($owed)) }}</strong>
        </div>
        @if ($owed > 0)
            <div class="inline actions">
                @if ($whatsApp)
                    <a class="button secondary" href="{{ $whatsApp }}" rel="noopener" target="_blank">Ask on WhatsApp</a>
                @endif
                <form method="POST" action="{{ route('people.settlements.in-full', $person) }}">
                    @csrf
                    <button type="submit" class="secondary">Paid it all back</button>
                </form>
            </div>
            <p class="muted small">If the money came into the bank, it shows below once the statement or email is in; choose it there instead.</p>
        @endif
    </section>

    @if ($possible->isNotEmpty())
        <section class="card">
            <h2>Is this {{ $person->name }} paying back?</h2>
            @foreach ($possible as $payment)
                <form method="POST" action="{{ route('people.settlements.from', [$person, $payment]) }}" class="row">
                    @csrf
                    <span>{{ $payment->description }} <span class="muted small block">{{ $payment->posted_on->format('j M Y') }}</span></span>
                    <span class="inline"><span class="amount in">{{ money($payment->amount_cents) }}</span><button type="submit" class="secondary">Yes</button></span>
                </form>
            @endforeach
        </section>
    @endif

    @if ($thisMonth->isNotEmpty())
        <section class="card">
            <h2>This month{{ $hasCard ? ' on the card' : '' }}</h2>
            @foreach ($thisMonth as $name => $cents)
                <div class="row"><span>{{ $name }}</span><span class="amount">{{ money($cents) }}</span></div>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>Paid back</h2>
        @forelse ($settlements as $settlement)
            <div class="row">
                <span>
                    {{ $settlement->transaction?->description ?? ($settlement->note ?: 'Recorded by hand') }}
                    <span class="muted small block">{{ $settlement->received_on->format('j M Y') }} · recorded by {{ $settlement->user->name }}</span>
                </span>
                <span class="inline">
                    <span class="amount in">{{ money($settlement->amount_cents) }}</span>
                    <form method="POST" action="{{ route('people.settlements.destroy', [$person, $settlement]) }}">
                        @csrf
                        <button type="submit" class="link" aria-label="Remove this repayment">Remove</button>
                    </form>
                </span>
            </div>
        @empty
            <p class="muted small">Nothing yet.</p>
        @endforelse

        <form method="POST" action="{{ route('people.settlements.store', $person) }}">
            @csrf
            <h2 class="spaced">Record a repayment</h2>
            <div class="pair">
                <span>
                    <label for="amount">Amount (R)</label>
                    <input type="number" name="amount" id="amount" step="0.01" min="0.01" required>
                </span>
                <span>
                    <label for="received_on">Date</label>
                    <input type="date" name="received_on" id="received_on" value="{{ now()->toDateString() }}" required>
                </span>
            </div>
            <label for="note">Note</label>
            <input type="text" name="note" id="note" maxlength="200" placeholder="For example: cash">
            <button type="submit">Record</button>
        </form>
    </section>

    <section class="card">
        <h2>{{ $hasCard ? 'Bought on the card' : 'Bought for '.$person->name }}</h2>
        @forelse ($charges->take(100) as $t)
            <a class="row" href="{{ route('transactions.edit', $t) }}">
                <span>{{ $t->description }} <span class="muted small block">{{ $t->posted_on->format('j M Y') }}@if ($t->card) · ••{{ $t->card->number_ending }}@endif · {{ $t->category->name ?? 'Not categorised' }}</span></span>
                <span @class(['amount', 'in' => $t->amount_cents > 0])>{{ money(-$t->amount_cents) }}</span>
            </a>
        @empty
            <p class="muted small">Nothing charged @if ($person->opening_balance_on) since {{ $person->opening_balance_on->format('j M Y') }} @else yet @endif.</p>
        @endforelse
    </section>

    <form method="POST" action="{{ route('people.update', $person) }}" class="card">
        @csrf
        <h2>Details</h2>
        <label for="name">Name</label>
        <input type="text" name="name" id="name" value="{{ old('name', $person->name) }}" required maxlength="100">

        <div class="pair">
            <span>
                <label for="opening_balance_on">Owed on</label>
                <input type="date" name="opening_balance_on" id="opening_balance_on" value="{{ old('opening_balance_on', $person->opening_balance_on?->toDateString()) }}">
            </span>
            <span>
                <label for="opening_balance">Amount (R)</label>
                <input type="number" name="opening_balance" id="opening_balance" step="0.01" value="{{ old('opening_balance', number_format($person->opening_balance_cents / 100, 2, '.', '')) }}" required>
            </span>
        </div>
        <p class="muted small">What {{ $person->name }} already owed at the end of that day, from before Budgeteer. Purchases after it are added; earlier ones are already in this amount. Leave the date empty and the amount 0 if nothing was owed.</p>

        <label for="payment_reference">Payments from {{ $person->name }} mention</label>
        <input type="text" name="payment_reference" id="payment_reference" value="{{ old('payment_reference', $person->payment_reference) }}" maxlength="100" placeholder="For example: {{ strtoupper(explode(' ', $person->name)[0]) }}">
        <p class="muted small">Payments into your accounts with this text are offered above as repayments.</p>

        <label for="phone">Cellphone, for WhatsApp</label>
        <input type="text" name="phone" id="phone" value="{{ old('phone', $person->phone) }}" maxlength="30" inputmode="tel">

        <button type="submit">Save</button>
    </form>
@endsection
