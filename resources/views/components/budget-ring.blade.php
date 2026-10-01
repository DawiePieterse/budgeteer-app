@props(['spent', 'budget'])
@php
    // The whole budget as one ring: the arc is the share spent, full and red once over.
    $over = $spent > $budget;
    $share = $budget > 0 ? min(1, max(0, $spent / $budget)) : 0;
    $pct = $budget > 0 ? (int) round($spent / $budget * 100) : 0;
    $r = 40;
    $circumference = 2 * M_PI * $r;
    $dash = round($share * $circumference, 2);
@endphp
<div {{ $attributes->class(['ring', 'is-over' => $over]) }} role="img" aria-label="All budget lines: {{ money($spent) }} of {{ money($budget) }} spent, {{ $over ? money($spent - $budget).' over' : money($budget - $spent).' left' }}">
    <svg viewBox="0 0 96 96" aria-hidden="true" focusable="false">
        <circle class="ring-track" cx="48" cy="48" r="{{ $r }}" fill="none" stroke-width="10"/>
        @if ($share > 0)
            <circle class="ring-arc" cx="48" cy="48" r="{{ $r }}" fill="none" stroke-width="10" stroke-linecap="round"
                stroke-dasharray="{{ $dash }} {{ round($circumference, 2) }}" transform="rotate(-90 48 48)"/>
        @endif
    </svg>
    <span class="ring-text" aria-hidden="true"><strong>{{ $pct }}%</strong><span>{{ $over ? 'over' : 'spent' }}</span></span>
</div>
