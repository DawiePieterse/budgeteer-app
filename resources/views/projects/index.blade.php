@extends('layouts.app')

@section('title', 'Special projects · Budgeteer')

@section('content')
    @include('partials.budget-tabs', ['current' => 'projects'])

    <p class="section-note">Spending on a special project, for example a car rebuild, is kept out of the monthly budget and shown on the project's own page.</p>

    @if ($projects->isNotEmpty())
        <div class="card">
            @foreach ($projects as $project)
                @php($spent = $project->spentCents())
                <a class="item owner-{{ $project->ownerColour() }}" href="{{ route('projects.show', $project) }}">
                    <span class="tile owned"><x-icon name="folder" /></span>
                    <span class="item-main">
                        <span class="item-title-row"><span class="item-title">{{ $project->name }}</span><span class="amount">{{ money($spent) }}</span></span>
                        @if ($project->budget_cents)
                            <x-bar :share="$spent / $project->budget_cents" :over="$spent > $project->budget_cents" />
                            <span class="item-sub">{{ (int) round($spent / $project->budget_cents * 100) }}% of {{ money($project->budget_cents) }}</span>
                        @else
                            <span class="item-sub">No project budget</span>
                        @endif
                    </span>
                    <x-icon name="forward" class="chev" :size="20" />
                </a>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('projects.store') }}" class="card pad">
        @csrf
        <h2>New project</h2>
        <div class="pair wide-first">
            <div class="field"><label for="project_name">Name</label><input type="text" name="name" id="project_name" maxlength="100" required></div>
            <div class="field"><label for="project_budget">Total budget (R)</label><input type="number" class="money" name="budget" id="project_budget" step="0.01" min="0" inputmode="decimal" placeholder="Optional"></div>
        </div>
        <button type="submit" class="secondary">Add project</button>
    </form>
@endsection
