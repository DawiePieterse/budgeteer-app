@extends('layouts.app')

@section('title', $project->name.' · Budgeteer')

@section('content')
    <x-back :href="route('projects.index')" label="Projects" />

    <section class="card hero owner-{{ $project->ownerColour() }}" aria-labelledby="project-h">
        <div class="group-head">
            <span class="tile lg owned"><x-icon name="folder" :size="26" /></span>
            <div class="item-main"><h1 id="project-h">{{ $project->name }}</h1><span class="item-sub">Special project, kept out of the monthly budget</span></div>
        </div>
        <div class="hero-text">
            <span class="hero-label">Spent so far</span>
            <span @class(['hero-value', 'over' => $project->budget_cents && $spent > $project->budget_cents])>{{ money($spent) }}</span>
        </div>
        @if ($project->budget_cents)
            @php($over = $spent > $project->budget_cents)
            <div class="meter" role="img" aria-label="Project budget: {{ money($spent) }} of {{ money($project->budget_cents) }}, {{ $over ? money($spent - $project->budget_cents).' over' : money($project->budget_cents - $spent).' left' }}">
                <x-bar class="lg" :share="$spent / $project->budget_cents" :over="$over" />
                <div class="meter-foot">
                    <span>{{ (int) round($spent / $project->budget_cents * 100) }}% of {{ money($project->budget_cents) }}</span>
                    <span @class(['over' => $over])>{{ $over ? money($spent - $project->budget_cents).' over' : money($project->budget_cents - $spent).' left' }}</span>
                </div>
            </div>
        @endif
    </section>

    @if ($byMonth->isNotEmpty())
        @php($most = max(1, $byMonth->max()))
        <section class="section" aria-labelledby="months-h">
            <div class="section-head"><h2 id="months-h">By month</h2></div>
            <div class="card bar-list">
                @foreach ($byMonth as $month => $cents)
                    @php($thisMonth = $month === now()->format('Y-m'))
                    <div @class(['bar-row', 'partial' => $thisMonth])>
                        <span class="label">{{ \Carbon\Carbon::parse($month.'-01')->format('M Y') }}</span>
                        <svg viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="month-fill" width="{{ $cents > 0 ? max(0.5, round($cents / $most * 100, 1)) : 0 }}" height="10"/></svg>
                        <span class="value">{{ money($cents) }}</span>
                    </div>
                @endforeach
                @if ($byMonth->has(now()->format('Y-m')))
                    <p class="hint">The lighter bar is this month so far.</p>
                @endif
            </div>
        </section>
    @endif

    <section class="section" aria-labelledby="payments-h">
        <div class="section-head"><h2 id="payments-h">Payments</h2><span class="muted">{{ $transactions->count() }}</span></div>
        <div class="card">
            @forelse ($transactions as $t)
                @include('transactions._row', ['t' => $t, 'showDate' => true])
            @empty
                <p class="empty">Nothing yet. On the Review screen, choose {{ $project->name }} for a shop, or set it on a transaction's page.</p>
            @endforelse
        </div>
    </section>

    @if ($merchants->isNotEmpty())
        <section class="section" aria-labelledby="auto-h">
            <div class="section-head"><h2 id="auto-h">Goes here automatically</h2></div>
            <p class="section-note">Later payments at these shops are added to the project.</p>
            <div class="chips">
                @foreach ($merchants as $key)
                    <span class="chip">
                        {{ readable($key) }}
                        <form method="POST" action="{{ route('projects.forget', [$project, $key]) }}">
                            @csrf
                            <button type="submit" aria-label="Stop sending {{ readable($key) }} here"><x-icon name="close" :size="16" /></button>
                        </form>
                    </span>
                @endforeach
            </div>
        </section>
    @endif

    <details class="card disclosure">
        <summary class="item">
            <span class="item-main"><span class="item-title">Name, budget and colour</span><span class="item-sub">{{ $project->name }}@if ($project->budget_cents) · {{ money($project->budget_cents) }}@endif</span></span>
            <x-icon name="down" class="chev down" :size="20" />
        </summary>
        <form method="POST" action="{{ route('projects.update', $project) }}" class="disclosure-body">
            @csrf
            <div class="pair wide-first">
                <div class="field"><label for="name">Name</label><input type="text" name="name" id="name" value="{{ $project->name }}" maxlength="100" required></div>
                <div class="field"><label for="budget">Total budget (R)</label><input type="number" class="money" name="budget" id="budget" step="0.01" min="0" inputmode="decimal" value="{{ $project->budget_cents !== null ? number_format($project->budget_cents / 100, 2, '.', '') : '' }}" placeholder="Optional"></div>
            </div>
            <x-colour-pick :current="$project->ownerColour()" />
            <button type="submit">Save</button>
        </form>
    </details>

    @php($count = $transactions->count())
    <details class="card danger disclosure">
        <summary class="item">
            <span class="tile danger"><x-icon name="trash" /></span>
            <span class="item-main"><span class="item-title out">Remove project</span><span class="item-sub">{{ $count === 0 ? 'It has no payments, so it can go' : 'Only once it has no payments left' }}</span></span>
            <x-icon name="forward" class="chev" :size="20" />
        </summary>
        <div class="disclosure-body">
            @if ($count === 0)
                <p class="hint">Takes {{ $project->name }} off Home and Budget › Projects.@if ($merchants->isNotEmpty()) Payments at {{ $merchants->map(fn ($key) => readable($key))->join(', ', ' and ') }} will no longer go to a project by themselves.@endif</p>
                <form method="POST" action="{{ route('projects.destroy', $project) }}">
                    @csrf
                    <button type="submit" class="outline danger wide">Remove {{ $project->name }}</button>
                </form>
            @else
                <p class="hint">{{ $project->name }} still has {{ $count }} {{ $count === 1 ? 'payment' : 'payments' }}. To remove it, open each one under Payments above and set <strong>Special project</strong> to <strong>None (monthly budget)</strong> or another project.</p>
            @endif
        </div>
    </details>
@endsection
