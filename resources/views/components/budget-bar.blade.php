@props(['spent', 'budget', 'label' => null, 'href' => null])
@php
    // The whole track is the budget (100%); the fill is what has been spent so far.
    $pct = $budget > 0 ? (int) round($spent / $budget * 100) : ($spent > 0 ? 100 : 0);
    $over = $spent > $budget;
    $fill = min(100, max($spent > 0 ? 1 : 0, $pct));
    $summary = money($spent).' of '.money($budget).' ('.$pct.'%)'.($over ? ', '.money($spent - $budget).' over' : ', '.money($budget - $spent).' left');
@endphp
<div {{ $attributes->class(['meter', 'is-over' => $over]) }}>
    <div class="meter-head">
        @if ($label)
            @if ($href)<a href="{{ $href }}">{{ $label }}</a>@else<span>{{ $label }}</span>@endif
        @endif
        <strong class="meter-pct">@if ($over)<span aria-hidden="true">⚠ </span>@endif{{ $pct }}%</strong>
    </div>
    <svg class="meter-bar" viewBox="0 0 100 10" preserveAspectRatio="none" role="img" aria-label="{{ $label ? $label.': ' : '' }}{{ $summary }}">
        <title>{{ $summary }}</title>
        <rect class="meter-track" x="0" y="0" width="100" height="10" />
        <rect class="meter-fill" x="0" y="0" width="{{ $fill }}" height="10" />
    </svg>
    <div class="meter-foot">
        <span>{{ money($spent) }} <span class="muted">of {{ money($budget) }}</span></span>
        <span @class(['over' => $over, 'muted' => ! $over])>{{ $over ? money($spent - $budget).' over' : money($budget - $spent).' left' }}</span>
    </div>
</div>
