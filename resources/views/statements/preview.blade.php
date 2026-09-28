@extends('layouts.app')

@section('title', 'Check statement · Budgeteer')

@section('content')
    <h1>Check the statement</h1>

    <section class="card">
        <div class="row"><span>Bank</span><span>{{ $statement->bank->label() }}</span></div>
        <div class="row"><span>Account</span><span>{{ $account?->name ?? $statement->accountKind->label() }} ••{{ $statement->accountNumberEnding }} @if (! $account) <span class="muted small block">new account</span> @endif</span></div>
        <div class="row"><span>Period</span><span>{{ $statement->from->format('j M Y') }} – {{ $statement->to->format('j M Y') }}</span></div>
        <div class="row"><span>Opening balance</span><span class="amount">{{ money($statement->openingCents) }}</span></div>
        <div class="row"><span>Money out</span><span class="amount">{{ money($statement->moneyOutCents()) }}</span></div>
        <div class="row"><span>Money in</span><span class="amount in">{{ money($statement->moneyInCents()) }}</span></div>
        <div class="row"><span>Closing balance</span><span class="amount">{{ money($statement->closingCents) }}</span></div>
        <p class="notice ok small">All {{ count($statement->lines) }} transactions add up to the closing balance.</p>
        @if ($alreadyThere > 0)
            <p class="muted small">{{ $alreadyThere }} of them are already in Budgeteer from an earlier statement and will be skipped.</p>
        @endif
    </section>

    @if ($alreadyImported)
        <p class="notice error">This statement was already imported on {{ $alreadyImported->created_at->format('j M Y') }}.</p>
        <a class="button secondary" href="{{ route('statements.index') }}">Back</a>
    @else
        <form method="POST" action="{{ route('statements.store') }}">
            @csrf
            <button type="submit">Import {{ count($statement->lines) - $alreadyThere }} transactions</button>
        </form>
        <a class="button secondary" href="{{ route('statements.index') }}">Cancel</a>
    @endif

    <section class="card">
        <h2>First transactions</h2>
        @foreach (array_slice($statement->lines, 0, 10) as $line)
            <div class="row">
                <span>{{ $line->description }} <span class="muted small block">{{ $line->date->format('j M') }}@if ($line->bankType) · {{ $line->bankType }}@endif</span></span>
                <span @class(['amount', 'in' => $line->amountCents > 0])>{{ money($line->amountCents) }}</span>
            </div>
        @endforeach
    </section>
@endsection
