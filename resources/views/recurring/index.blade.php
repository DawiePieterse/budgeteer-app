@extends('layouts.app')

@section('title', 'Recurring payments · Budgeteer')

@section('content')
    <nav class="period" aria-label="Budget period">
        <a href="{{ route('recurring.index', ['in' => $period->previous()->from->toDateString()]) }}" aria-label="Previous period">‹</a>
        <h1>Recurring · {{ $period->label() }}</h1>
        <a href="{{ route('recurring.index', ['in' => $period->next()->from->toDateString()]) }}" aria-label="Next period">›</a>
    </nav>

    @php($counts = collect($occurrences)->countBy(fn ($o) => $o->status))
    @if ($occurrences !== [])
        <p class="muted small">
            {{ ($counts['paid'] ?? 0) + ($counts['paid_by_hand'] ?? 0) }} of {{ count($occurrences) }} paid
            @if ($counts['changed'] ?? 0) · <span class="over">⚠ {{ $counts['changed'] }} changed</span>@endif
            @if ($counts['late'] ?? 0) · <span class="over">⚠ {{ $counts['late'] }} late</span>@endif
            @if ($counts['due'] ?? 0) · {{ $counts['due'] }} still due @endif
        </p>
    @endif

    <section class="card">
        @forelse ($occurrences as $o)
            <div class="row recurring-row">
                <span>
                    <strong>{{ $o->payment->name }}</strong>
                    <span class="muted small block">{{ $o->dueOn->format('D j M') }} · {{ $o->payment->category?->name ?? 'No budget line' }}</span>
                </span>
                <span class="amount">
                    @switch($o->status)
                        @case('paid')
                            <span class="status ok">✓ Paid</span>
                            <span class="block">{{ money(abs($o->transaction->amount_cents)) }}</span>
                            @break
                        @case('changed')
                            <span class="status over">⚠ Amount changed</span>
                            <span class="block">{{ money(abs($o->transaction->amount_cents)) }}</span>
                            <span class="muted small block">expected {{ money($o->payment->amount_cents) }}</span>
                            @break
                        @case('late')
                            <span class="status over">⚠ Late</span>
                            <span class="muted small block">{{ money($o->payment->amount_cents) }} not seen</span>
                            @break
                        @case('skipped')
                            <span class="status">Skipped</span>
                            @break
                        @case('paid_by_hand')
                            <span class="status ok">✓ Paid (by hand)</span>
                            @break
                        @default
                            <span class="status">Due</span>
                            <span class="block">{{ money($o->payment->amount_cents) }}</span>
                    @endswitch
                </span>
                @if ($o->status === 'changed')
                    <form method="POST" action="{{ route('recurring.use-amount', [$o->payment, $o->transaction]) }}" class="actions inline">
                        @csrf
                        <button type="submit" class="secondary">Expect {{ money(abs($o->transaction->amount_cents)) }} from now on</button>
                    </form>
                @elseif (in_array($o->status, ['late', 'due'], true))
                    <span class="actions inline">
                        @foreach (['paid' => 'Paid elsewhere', 'skipped' => 'Skip this time'] as $status => $label)
                            <form method="POST" action="{{ route('recurring.mark', $o->payment) }}">
                                @csrf
                                <input type="hidden" name="due_on" value="{{ $o->dueOn->toDateString() }}">
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button type="submit" class="link">{{ $label }}</button>
                            </form>
                        @endforeach
                    </span>
                @elseif (in_array($o->status, ['skipped', 'paid_by_hand'], true))
                    <form method="POST" action="{{ route('recurring.mark', $o->payment) }}" class="actions inline">
                        @csrf
                        <input type="hidden" name="due_on" value="{{ $o->dueOn->toDateString() }}">
                        <input type="hidden" name="status" value="clear">
                        <button type="submit" class="link">Undo</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="muted small">No recurring payments yet. Add the suggestions below, or your own.</p>
        @endforelse
    </section>

    @if ($suggestions !== [])
        <section class="card">
            <h2>Looks recurring</h2>
            <p class="muted small">These come back at a similar amount every {{ '' }}month or week. Add them to be told when one is late or changes.</p>
            @foreach ($suggestions as $s)
                <form method="POST" action="{{ route('recurring.store') }}" class="row">
                    @csrf
                    @foreach (['match_text', 'category_id', 'amount_varies', 'frequency', 'day'] as $field)
                        <input type="hidden" name="{{ $field }}" value="{{ is_bool($s[$field]) ? (int) $s[$field] : $s[$field] }}">
                    @endforeach
                    <input type="hidden" name="amount" value="{{ number_format($s['amount_cents'] / 100, 2, '.', '') }}">
                    <span>
                        <label class="visually-hidden" for="suggest-{{ $loop->index }}">Name</label>
                        <input type="text" name="name" id="suggest-{{ $loop->index }}" value="{{ $s['name'] }}" maxlength="100" required>
                        <span class="muted small block">
                            {{ $s['frequency'] === 'weekly' ? 'Weekly' : 'Monthly, around the '.$s['day'].date('S', mktime(0, 0, 0, 1, $s['day'])) }} · seen {{ $s['seen'] }} times{{ $s['amount_varies'] ? ' · amount varies' : '' }}
                        </span>
                    </span>
                    <span class="inline"><span class="amount">{{ money($s['amount_cents']) }}</span><button type="submit" class="secondary">Add</button></span>
                </form>
            @endforeach
        </section>
    @endif

    @if ($payments->isNotEmpty())
        <section class="card">
            <h2>All recurring payments</h2>
            @foreach ($payments as $payment)
                <details class="recurring-edit">
                    <summary>
                        {{ $payment->name }} · {{ money($payment->amount_cents) }}{{ $payment->amount_varies ? ' (varies)' : '' }}
                        <span class="muted small">· {{ $payment->describeSchedule() }}@unless ($payment->active) · stopped @endunless</span>
                    </summary>
                    <form method="POST" action="{{ route('recurring.update', $payment) }}">
                        @csrf
                        @include('recurring._fields', ['payment' => $payment])
                        <button type="submit">Save</button>
                    </form>
                    <form method="POST" action="{{ route('recurring.destroy', $payment) }}">
                        @csrf
                        <button type="submit" class="link">Stop tracking {{ $payment->name }}</button>
                    </form>
                </details>
            @endforeach
        </section>
    @endif

    <form method="POST" action="{{ route('recurring.store') }}" class="card">
        @csrf
        <h2>Add a recurring payment</h2>
        @include('recurring._fields', ['payment' => null])
        <button type="submit">Add</button>
    </form>
@endsection
