@extends('layouts.app')

@section('title', $person->name.' · Budgeteer')

@section('content')
    @php($cards = $person->cards()->pluck('number_ending'))
    <x-back :href="route('home')" label="Home" />

    <section class="card hero owner-{{ $person->ownerColour() }}" aria-labelledby="person-h">
        <div class="group-head">
            <span class="avatar lg owned" aria-hidden="true">{{ mb_substr($person->name, 0, 1) }}</span>
            <div class="item-main">
                <h1 id="person-h">{{ $person->name }}</h1>
                <span class="item-sub">@if ($cards->isNotEmpty()) Card {{ $cards->map(fn ($n) => '••'.$n)->join(', ') }} charged to {{ $person->name }} @else Things bought for {{ $person->name }} @endif</span>
            </div>
        </div>
        <div class="hero-text">
            @if ($owed === 0)
                <span class="hero-label">All square</span>
                <span class="hero-value in">R0.00</span>
                <span class="hero-note">{{ $person->name }} owes you nothing.</span>
            @else
                <span class="hero-label">{{ $owed > 0 ? 'Owes you' : 'You owe '.$person->name }}</span>
                <span class="hero-value">{{ money(abs($owed)) }}</span>
                <span class="hero-note">@if ($person->opening_balance_on) Since {{ $person->opening_balance_on->format('j M Y') }}, less repayments @else Less repayments @endif</span>
            @endif
        </div>
        @if ($owed > 0)
            <div @class(['actions', 'two' => $whatsApp])>
                @if ($whatsApp)
                    <a class="button" href="{{ $whatsApp }}" rel="noopener" target="_blank"><x-icon name="chat" :size="20" /> Ask on WhatsApp</a>
                @endif
                <form method="POST" action="{{ route('people.settlements.in-full', $person) }}">
                    @csrf
                    <button type="submit" @class(['outline', 'wide' => ! $whatsApp])>Paid it all back</button>
                </form>
            </div>
            <p class="hint">If the money came into the bank, it shows below once the statement or email is in; choose it there instead.</p>
        @endif
    </section>

    @if ($possible->isNotEmpty())
        <section class="section" aria-labelledby="possible-h">
            <div class="notice info">
                <div class="notice-body">
                    <h2 id="possible-h">Is this {{ $person->name }} paying back?</h2>
                    <div class="card">
                        @foreach ($possible as $payment)
                            <form method="POST" action="{{ route('people.settlements.from', [$person, $payment]) }}" class="item">
                                @csrf
                                <span class="item-main"><span class="item-title">{{ $payment->displayName() }}</span><span class="item-sub">{{ $payment->posted_on->format('D j M Y') }}</span></span>
                                <span class="amount in">+{{ money($payment->amount_cents) }}</span>
                                <button type="submit" class="sm">Yes</button>
                            </form>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($thisMonth->isNotEmpty())
        <section class="section" aria-labelledby="month-h">
            <div class="section-head"><h2 id="month-h">This month{{ $hasCard ? ' on the card' : '' }}</h2><span class="muted">{{ money($thisMonth->sum()) }}</span></div>
            <div class="card">
                @foreach ($thisMonth as $name => $cents)
                    <div class="kv"><span>{{ $name }}</span><span class="amount">{{ money($cents) }}</span></div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="back-h">
        <div class="section-head"><h2 id="back-h">Paid back</h2></div>
        <div class="card">
            @foreach ($settlements as $settlement)
                <div class="item">
                    <span class="item-main">
                        <span class="item-title">{{ $settlement->transaction ? $settlement->transaction->displayName() : ($settlement->note ?: 'Recorded by hand') }}</span>
                        <span class="item-sub">{{ $settlement->received_on->format('D j M Y') }} · recorded by {{ $settlement->user->name }}</span>
                    </span>
                    <span class="amount in">+{{ money($settlement->amount_cents) }}</span>
                    <form method="POST" action="{{ route('people.settlements.destroy', [$person, $settlement]) }}">
                        @csrf
                        <button type="submit" class="icon-button" aria-label="Remove this repayment"><x-icon name="trash" :size="18" /></button>
                    </form>
                </div>
            @endforeach
            <details class="disclosure" @if ($errors->hasAny(['amount', 'received_on', 'note'])) open @endif>
                <summary class="item">
                    <span class="tile accent"><x-icon name="plus" /></span>
                    <span class="item-main"><span class="item-title">Record a repayment</span><span class="item-sub">Cash, or money that came in some other way</span></span>
                    <x-icon name="forward" class="chev" :size="20" />
                </summary>
                <form method="POST" action="{{ route('people.settlements.store', $person) }}" class="disclosure-body">
                    @csrf
                    <div class="pair">
                        <div class="field"><label for="amount">Amount (R)</label><input type="number" class="money" name="amount" id="amount" step="0.01" min="0.01" inputmode="decimal" required></div>
                        <div class="field"><label for="received_on">Date</label><input type="date" name="received_on" id="received_on" value="{{ now()->toDateString() }}" required></div>
                    </div>
                    <div class="field"><label for="note">Note</label><input type="text" name="note" id="note" maxlength="200" placeholder="For example: cash"></div>
                    <button type="submit">Record</button>
                </form>
            </details>
        </div>
    </section>

    <section class="section" aria-labelledby="bought-h">
        <div class="section-head"><h2 id="bought-h">{{ $hasCard ? 'Bought on the card' : 'Bought for '.$person->name }}</h2></div>
        <div class="card">
            @forelse ($charges->take(100) as $t)
                <a class="item" href="{{ route('transactions.edit', $t) }}">
                    <span class="item-main">
                        <span class="item-title clip">{{ $t->displayName() }}</span>
                        <span class="item-sub">{{ $t->posted_on->format('j M Y') }}@if ($t->card) · ••{{ $t->card->number_ending }}@endif · {{ $t->category->name ?? 'Not categorised' }}</span>
                    </span>
                    <span @class(['amount', 'in' => $t->amount_cents > 0])>{{ money(-$t->amount_cents) }}</span>
                </a>
            @empty
                <p class="empty">Nothing charged @if ($person->opening_balance_on) since {{ $person->opening_balance_on->format('j M Y') }} @else yet @endif.</p>
            @endforelse
        </div>
    </section>

    <details class="card disclosure" @if ($errors->hasAny(['name', 'phone', 'opening_balance', 'opening_balance_on', 'payment_reference', 'colour'])) open @endif>
        <summary class="item">
            <span class="item-main"><span class="item-title">Details</span><span class="item-sub">Opening balance, payment reference, cellphone, colour</span></span>
            <x-icon name="down" class="chev down" :size="20" />
        </summary>
        <form method="POST" action="{{ route('people.update', $person) }}" class="disclosure-body">
            @csrf
            <div class="field"><label for="name">Name</label><input type="text" name="name" id="name" value="{{ old('name', $person->name) }}" required maxlength="100"></div>
            <div class="pair">
                <div class="field"><label for="opening_balance_on">Owed on</label><input type="date" name="opening_balance_on" id="opening_balance_on" value="{{ old('opening_balance_on', $person->opening_balance_on?->toDateString()) }}"></div>
                <div class="field"><label for="opening_balance">Amount (R)</label><input type="number" class="money" name="opening_balance" id="opening_balance" step="0.01" inputmode="decimal" value="{{ old('opening_balance', number_format($person->opening_balance_cents / 100, 2, '.', '')) }}" required></div>
            </div>
            <p class="hint">What {{ $person->name }} already owed at the end of that day, from before Budgeteer. Purchases after it are added; earlier ones are already in this amount. Leave the date empty and the amount 0 if nothing was owed.</p>
            <div class="field">
                <label for="payment_reference">Payments from {{ $person->name }} mention</label>
                <input type="text" name="payment_reference" id="payment_reference" value="{{ old('payment_reference', $person->payment_reference) }}" maxlength="100" placeholder="For example: {{ strtoupper(explode(' ', $person->name)[0]) }}">
                <span class="hint">Payments into your accounts with this text are offered above as repayments.</span>
            </div>
            <div class="field"><label for="phone">Cellphone, for WhatsApp</label><input type="tel" name="phone" id="phone" value="{{ old('phone', $person->phone) }}" maxlength="30" inputmode="tel"></div>
            <x-colour-pick :current="$person->ownerColour()" />
            <button type="submit">Save</button>
        </form>
    </details>
@endsection
