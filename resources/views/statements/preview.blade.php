@extends('layouts.app')

@section('title', 'Check the statement · Budgeteer')

@section('content')
    @php($toImport = max(0, count($statement->lines) - $alreadyThere - $tooOld))
    <a class="back" href="{{ route('statements.index') }}"><x-icon name="close" /> Cancel</a>
    <header class="page-head"><h1>Check the statement</h1></header>

    <div class="notice ok" role="status">
        <x-icon name="check" :size="20" />
        <p><strong>All {{ count($statement->lines) }} transactions add up</strong> to the closing balance.</p>
    </div>

    <dl class="card">
        <div class="kv"><dt>Bank</dt><dd>{{ $statement->bank->label() }}</dd></div>
        <div class="kv"><dt>Account</dt><dd>{{ $account?->name ?? $statement->accountKind->label() }} ••{{ $statement->accountNumberEnding }} @if (! $account) <span class="muted small block">new account</span> @endif</dd></div>
        <div class="kv"><dt>Period</dt><dd>{{ $statement->from->format('j M Y') }} – {{ $statement->to->format('j M Y') }}</dd></div>
    </dl>

    <section class="card sum" aria-label="Balance">
        <div class="sum-row"><span></span><span>Opening balance</span><span>{{ money($statement->openingCents) }}</span></div>
        <div class="sum-row"><span class="op" aria-hidden="true">−</span><span>Money out</span><span>{{ money($statement->moneyOutCents()) }}</span></div>
        <div class="sum-row"><span class="op" aria-hidden="true">+</span><span>Money in</span><span class="in">{{ money($statement->moneyInCents()) }}</span></div>
        <div class="sum-row total"><span aria-hidden="true">=</span><span>Closing balance</span><span>{{ money($statement->closingCents) }}</span></div>
    </section>

    @if ($tooOld > 0)
        <p class="notice plain"><x-icon name="info" :size="20" /> {{ $tooOld }} are dated before {{ $keepFrom->format('j F Y') }}, which Budgeteer does not keep, and will be skipped.</p>
    @endif
    @if ($alreadyThere > 0)
        <p class="notice plain"><x-icon name="info" :size="20" /> {{ $alreadyThere }} of them are already in Budgeteer from an earlier statement and will be skipped.</p>
    @endif

    @if ($alreadyImported)
        <div class="notice error" role="alert"><x-icon name="alert" :size="20" /><p>This statement was already imported on {{ $alreadyImported->created_at->format('j M Y') }}.</p></div>
        <a class="button outline lg" href="{{ route('statements.index') }}">Back</a>
    @else
        <div class="stack">
            <form method="POST" action="{{ route('statements.store') }}">
                @csrf
                <button type="submit" class="lg wide">Import {{ $toImport }} transactions</button>
            </form>
            <a class="button ghost" href="{{ route('statements.index') }}">Cancel</a>
        </div>
    @endif

    <section class="section" aria-labelledby="first-h">
        <div class="section-head"><h2 id="first-h">First transactions</h2></div>
        <div class="card">
            @foreach (array_slice($statement->lines, 0, 10) as $line)
                <div class="item">
                    <span class="item-main"><span class="item-title clip">{{ readable($line->description) }}</span><span class="item-sub">{{ $line->date->format('j M') }}@if ($line->bankType) · {{ $line->bankType }}@endif</span></span>
                    <span @class(['amount', 'in' => $line->amountCents > 0])>{{ $line->amountCents > 0 ? '+' : '' }}{{ money($line->amountCents) }}</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
