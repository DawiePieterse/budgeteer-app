@props(['spent', 'budget'])
@php
    // A small ring of the budget used, for the Overview's Left to spend; full and red once over.
    $over = $spent > $budget;
    $share = $budget > 0 ? min(1, max(0, $spent / $budget)) : 0;
    $r = 18;
    $circumference = 2 * M_PI * $r;
@endphp
<svg {{ $attributes->class(['ov-ring', 'is-over' => $over]) }} width="44" height="44" viewBox="0 0 44 44" role="img" aria-label="{{ (int) round($budget > 0 ? $spent / $budget * 100 : 0) }}% of the budget used">
    <circle class="ov-ring-track" cx="22" cy="22" r="{{ $r }}" fill="none" stroke-width="5"/>
    @if ($share > 0)
        <circle class="ov-ring-arc" cx="22" cy="22" r="{{ $r }}" fill="none" stroke-width="5" stroke-linecap="round"
            stroke-dasharray="{{ round($share * $circumference, 2) }} {{ round($circumference, 2) }}" transform="rotate(-90 22 22)"/>
    @endif
</svg>
