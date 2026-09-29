@props(['spent', 'budget', 'label', 'href' => null])
@php
    $pct = $budget > 0 ? (int) round($spent / $budget * 100) : ($spent > 0 ? 100 : 0);
    $over = $spent > $budget;
    $summary = $label.': '.money($spent).' of '.money($budget).' ('.$pct.'%)'.($over ? ', '.money($spent - $budget).' over' : ', '.money($budget - $spent).' left');
@endphp
<{{ $href ? 'a' : 'div' }} {{ $attributes->class(['line', 'is-over' => $over]) }} @if ($href) href="{{ $href }}" @endif aria-label="{{ $summary }}">
    <span class="line-name">
        {{ $label }}
        <span class="line-of">{{ money($spent) }} <span class="muted">of {{ money($budget) }}</span></span>
    </span>
    <span class="line-right">
        <span @class(['line-left', 'over' => $over, 'muted' => ! $over])>{{ $over ? money($spent - $budget).' over' : money($budget - $spent).' left' }}</span>
        <strong class="line-pct">@if ($over)<span aria-hidden="true">⚠</span> @endif{{ $pct }}%</strong>
    </span>
</{{ $href ? 'a' : 'div' }}>
