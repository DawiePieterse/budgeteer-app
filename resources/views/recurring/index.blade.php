@extends('layouts.app')

@section('title', 'Recurring payments · Budgeteer')

@section('content')
    @include('partials.budget-tabs', ['current' => 'recurring'])

    @php
        $all = collect($occurrences);
        $attention = $all->filter->needsAttention();
        $due = $all->where('status', 'due');
        $done = $all->whereIn('status', ['paid', 'paid_by_hand', 'skipped']);
        $paid = $all->whereIn('status', ['paid', 'paid_by_hand'])->count();
    @endphp

    <div class="stack">
        @include('partials.period', ['route' => 'recurring.index', 'class' => 'flat'])

        @if ($all->isNotEmpty())
            @php
                // Paid (and skipped), needing attention and still due, as one bar of the month's payments.
                $parts = array_filter(['paid' => $done->count(), 'attention' => $attention->count(), 'due' => $due->count()]);
                $gap = 0.8;
                $width = 100 - $gap * (count($parts) - 1);
                $x = 0;
            @endphp
            <section class="card summary-card" aria-label="This month so far">
                <strong class="summary-value">{{ $paid }} of {{ $all->count() }} paid</strong>
                <svg class="stack-bar" viewBox="0 0 100 10" preserveAspectRatio="none" role="img" aria-label="{{ $done->count() }} paid or skipped, {{ $attention->count() }} need attention, {{ $due->count() }} still due">
                    @foreach ($parts as $part => $count)
                        @php($w = round($count / $all->count() * $width, 2))
                        <rect class="{{ $part }}" x="{{ $x }}" width="{{ $w }}" height="10" rx="0.6"/>
                        @php($x = round($x + $w + $gap, 2))
                    @endforeach
                </svg>
                <p class="legend">
                    <span><i class="paid"></i>Paid or skipped {{ $done->count() }}</span>
                    <span><i class="attention"></i>Needs attention {{ $attention->count() }}</span>
                    <span><i class="due"></i>Still due {{ $due->count() }}</span>
                </p>
            </section>
        @endif
    </div>

    @if ($attention->isNotEmpty())
        <section class="section" aria-labelledby="attention-h">
            <div class="section-head"><h2 id="attention-h">Needs attention</h2></div>
            @foreach ($attention as $o)
                <article class="card pad">
                    <div class="group-head">
                        <span class="tile warn"><x-icon :name="$o->status === 'late' ? 'clock' : 'alert'" /></span>
                        <span class="item-main"><span class="item-title">{{ $o->payment->name }}</span><span class="item-sub">{{ $o->dueOn->format('D j M') }} · {{ $o->payment->category?->name ?? 'No budget line' }}</span></span>
                        <span class="item-end">
                            <span class="pill warn">{{ $o->label() }}</span>
                            @if ($o->status === 'changed')
                                <span class="amount">{{ money(abs($o->transaction->amount_cents)) }}</span>
                                <span class="item-sub">expected {{ money($o->payment->amount_cents) }}</span>
                            @else
                                <span class="amount">{{ money($o->payment->amount_cents) }}</span>
                                <span class="item-sub">not seen yet</span>
                            @endif
                        </span>
                    </div>
                    @if ($o->status === 'changed')
                        <form method="POST" action="{{ route('recurring.use-amount', [$o->payment, $o->transaction]) }}">
                            @csrf
                            <button type="submit" class="secondary wide">Expect {{ money(abs($o->transaction->amount_cents)) }} from now on</button>
                        </form>
                    @else
                        <div class="actions two">
                            @foreach (['paid' => ['Paid elsewhere', 'secondary'], 'skipped' => ['Skip this time', 'outline']] as $status => [$label, $style])
                                <form method="POST" action="{{ route('recurring.mark', $o->payment) }}">
                                    @csrf
                                    <input type="hidden" name="due_on" value="{{ $o->dueOn->toDateString() }}">
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" class="{{ $style }}">{{ $label }}</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </section>
    @endif

    @if ($due->isNotEmpty())
        <section class="section" aria-labelledby="due-h">
            <div class="section-head"><h2 id="due-h">Still due</h2></div>
            <div class="card">
                @foreach ($due as $o)
                    <div class="item wrap">
                        <span class="tile"><x-icon name="calendar" /></span>
                        <span class="item-main"><span class="item-title">{{ $o->payment->name }}</span><span class="item-sub">{{ $o->dueOn->format('D j M') }} · {{ $o->payment->category?->name ?? 'No budget line' }}</span></span>
                        <span class="item-end"><span class="amount">{{ money($o->payment->amount_cents) }}</span><span class="item-sub">{{ $o->label() }}</span></span>
                        <span class="actions">
                            @foreach (['paid' => 'Paid elsewhere', 'skipped' => 'Skip this time'] as $status => $label)
                                <form method="POST" action="{{ route('recurring.mark', $o->payment) }}">
                                    @csrf
                                    <input type="hidden" name="due_on" value="{{ $o->dueOn->toDateString() }}">
                                    <input type="hidden" name="status" value="{{ $status }}">
                                    <button type="submit" class="link">{{ $label }}</button>
                                </form>
                            @endforeach
                        </span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($done->isNotEmpty())
        <section class="section" aria-labelledby="done-h">
            <div class="section-head"><h2 id="done-h">Paid or skipped</h2></div>
            <div class="card">
                @foreach ($done as $o)
                    <div class="item">
                        <span @class(['tile', 'ok' => $o->status !== 'skipped'])><x-icon :name="$o->status === 'skipped' ? 'skip' : 'check'" /></span>
                        <span class="item-main"><span class="item-title">{{ $o->payment->name }}</span><span class="item-sub">{{ $o->dueOn->format('D j M') }} · {{ $o->payment->category?->name ?? 'No budget line' }}</span></span>
                        <span class="item-end">
                            @if ($o->transaction)<span class="amount">{{ money(abs($o->transaction->amount_cents)) }}</span>@endif
                            <span @class(['status', 'ok' => $o->status !== 'skipped'])>{{ $o->label() }}</span>
                        </span>
                        @if (in_array($o->status, ['skipped', 'paid_by_hand'], true))
                            <form method="POST" action="{{ route('recurring.mark', $o->payment) }}">
                                @csrf
                                <input type="hidden" name="due_on" value="{{ $o->dueOn->toDateString() }}">
                                <input type="hidden" name="status" value="clear">
                                <button type="submit" class="link">Undo</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($all->isEmpty())
        <div class="card"><p class="empty">No recurring payments yet. Add the suggestions below, or your own.</p></div>
    @endif

    @if ($suggestions !== [])
        <section class="section" aria-labelledby="suggest-h">
            <div class="section-head"><h2 id="suggest-h">Looks recurring</h2></div>
            <p class="section-note">These come back at a similar amount every month or week. Add them to hear when one is late or changes.</p>
            <div class="card">
                @foreach ($suggestions as $s)
                    <form method="POST" action="{{ route('recurring.store') }}" class="item">
                        @csrf
                        @foreach (['match_text', 'category_id', 'amount_varies', 'frequency', 'day'] as $field)
                            <input type="hidden" name="{{ $field }}" value="{{ is_bool($s[$field]) ? (int) $s[$field] : $s[$field] }}">
                        @endforeach
                        <input type="hidden" name="amount" value="{{ number_format($s['amount_cents'] / 100, 2, '.', '') }}">
                        <span class="item-main">
                            <label class="visually-hidden" for="suggest-{{ $loop->index }}">Name</label>
                            <input type="text" name="name" id="suggest-{{ $loop->index }}" value="{{ $s['name'] }}" maxlength="100" required>
                            <span class="item-sub">{{ $s['frequency'] === 'weekly' ? 'Weekly' : 'Monthly, around the '.$s['day'].date('S', mktime(0, 0, 0, 1, $s['day'])) }} · seen {{ $s['seen'] }} times{{ $s['amount_varies'] ? ' · amount varies' : '' }}</span>
                        </span>
                        <span class="item-end"><span class="amount">{{ money($s['amount_cents']) }}</span><button type="submit" class="secondary sm">Add</button></span>
                    </form>
                @endforeach
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="manage-h">
        <div class="section-head"><h2 id="manage-h">{{ $payments->isEmpty() ? 'Add one' : 'All recurring payments' }}</h2></div>
        <div class="card">
            @foreach ($payments as $payment)
                <details class="disclosure">
                    <summary class="item">
                        <span class="item-main">
                            <span class="item-title">{{ $payment->name }}</span>
                            <span class="item-sub">{{ money($payment->amount_cents) }}{{ $payment->amount_varies ? ' (varies)' : '' }} · {{ $payment->describeSchedule() }}@unless ($payment->active) · stopped @endunless</span>
                        </span>
                        <x-icon name="forward" class="chev" :size="20" />
                    </summary>
                    <div class="disclosure-body">
                        <form method="POST" action="{{ route('recurring.update', $payment) }}" class="fields">
                            @csrf
                            @include('recurring._fields', ['payment' => $payment])
                            <button type="submit">Save</button>
                        </form>
                        <form method="POST" action="{{ route('recurring.destroy', $payment) }}">
                            @csrf
                            <button type="submit" class="link danger">Stop tracking {{ $payment->name }}</button>
                        </form>
                    </div>
                </details>
            @endforeach
            <details class="disclosure" @if ($payments->isEmpty() && $suggestions === []) open @endif>
                <summary class="item">
                    <span class="tile accent"><x-icon name="plus" /></span>
                    <span class="item-main"><span class="item-title">Add a recurring payment</span><span class="item-sub">A debit order, levy or anything else that comes back</span></span>
                    <x-icon name="forward" class="chev" :size="20" />
                </summary>
                <form method="POST" action="{{ route('recurring.store') }}" class="disclosure-body">
                    @csrf
                    @include('recurring._fields', ['payment' => null])
                    <button type="submit">Add</button>
                </form>
            </details>
        </div>
    </section>
@endsection
