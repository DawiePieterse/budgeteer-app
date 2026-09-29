@props(['spent', 'budget'])
@php
    // The whole budget as one ring: the arc is the share spent, full and red once over.
    $over = $spent > $budget;
    $share = $budget > 0 ? min(1, max(0, $spent / $budget)) : 0;
    $r = 70;
    $circumference = 2 * M_PI * $r;
    $dash = round($share * $circumference, 2);
@endphp
<div {{ $attributes->class(['ring', 'is-over' => $over]) }} role="img" aria-label="All budget lines: {{ money($spent) }} of {{ money($budget) }} spent, {{ $over ? money($spent - $budget).' over' : money($budget - $spent).' left' }}">
    <svg viewBox="0 0 160 160" width="160" height="160" aria-hidden="true" focusable="false">
        <circle class="ring-track" cx="80" cy="80" r="{{ $r }}" fill="none" stroke-width="12"/>
        @if ($share > 0)
            <circle class="ring-arc" cx="80" cy="80" r="{{ $r }}" fill="none" stroke-width="12" stroke-linecap="round"
                stroke-dasharray="{{ $dash }} {{ round($circumference, 2) }}" transform="rotate(-90 80 80)"/>
        @endif
    </svg>
    <span class="ring-text">
        <span class="muted small">Spent</span>
        <strong class="ring-spent">{{ rand_whole($spent) }}</strong>
        <span class="muted small">of {{ rand_whole($budget) }}</span>
        <span @class(['ring-left', 'over' => $over])>@if ($over)<span aria-hidden="true">⚠</span> {{ rand_whole($spent - $budget) }} over @else {{ rand_whole($budget - $spent) }} left @endif</span>
    </span>
</div>
