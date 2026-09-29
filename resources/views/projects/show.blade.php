@extends('layouts.app')

@section('title', $project->name.' · Budgeteer')

@section('content')
    <h1>{{ $project->name }}</h1>
    <p class="muted small">A special project: kept out of the monthly budget.</p>

    <section class="card">
        <div class="row">
            <span><strong>Spent so far</strong></span>
            <strong class="amount">{{ money($spent) }}</strong>
        </div>
        @if ($project->budget_cents)
            <x-budget-bar :spent="$spent" :budget="$project->budget_cents" label="Project budget" />
        @endif
    </section>

    @if ($byMonth->isNotEmpty())
        <section class="card">
            <h2>By month</h2>
            @foreach ($byMonth as $month => $cents)
                <div class="row"><span>{{ \Carbon\Carbon::parse($month.'-01')->format('F Y') }}</span><span class="amount">{{ money($cents) }}</span></div>
            @endforeach
        </section>
    @endif

    <section class="card">
        <h2>Payments</h2>
        @forelse ($transactions as $t)
            <a class="row" href="{{ route('transactions.edit', $t) }}">
                <span>{{ $t->description }} <span class="muted small block">{{ $t->posted_on->format('j M Y') }} · {{ $t->account->name }}</span></span>
                <span @class(['amount', 'in' => $t->amount_cents > 0])>{{ money(-$t->amount_cents) }}</span>
            </a>
        @empty
            <p class="muted small">Nothing yet. On the Categorise screen, choose {{ $project->name }} for a merchant, or set it on a transaction's page.</p>
        @endforelse
    </section>

    @if ($merchants->isNotEmpty())
        <section class="card">
            <h2>Goes here automatically</h2>
            @foreach ($merchants as $key)
                <form method="POST" action="{{ route('projects.forget', [$project, $key]) }}" class="row">
                    @csrf
                    <span>{{ $key }}</span>
                    <button type="submit" class="link">Stop</button>
                </form>
            @endforeach
        </section>
    @endif

    <form method="POST" action="{{ route('projects.update', $project) }}" class="card">
        @csrf
        <h2>Details</h2>
        <div class="pair">
            <span><label for="name">Name</label><input type="text" name="name" id="name" value="{{ $project->name }}" maxlength="100" required></span>
            <span><label for="budget">Total budget (R)</label><input type="number" name="budget" id="budget" step="0.01" min="0" value="{{ $project->budget_cents !== null ? number_format($project->budget_cents / 100, 2, '.', '') : '' }}" placeholder="Optional"></span>
        </div>
        <x-colour-pick :current="$project->ownerColour()" />
        <button type="submit">Save</button>
    </form>
@endsection
