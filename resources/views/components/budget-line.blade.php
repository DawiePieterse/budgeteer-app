@props(['spent', 'budget', 'label', 'href' => null])
@php
    // Compact budget line: the thin track is the whole budget (100%), the fill is spent so far.
    $pct = $budget > 0 ? (int) round($spent / $budget * 100) : ($spent > 0 ? 100 : 0);
    $over = $spent > $budget;
    $fill = min(100, max($spent > 0 ? 1 : 0, $pct));
    $summary = $label.': '.money($spent).' of '.money($budget).' ('.$pct.'%)'.($over ? ', '.money($spent - $budget).' over' : ', '.money($budget - $spent).' left');
@endphp
<a {{ $attributes->class(['line', 'is-over' => $over]) }} href="{{ $href }}" title="{{ $summary }}" aria-label="{{ $summary }}">
    <span class="line-name">{{ $label }}</span>
    <span @class(['line-left', 'over' => $over, 'muted' => ! $over])>{{ $over ? money($spent - $budget).' over' : money($budget - $spent).' left' }}</span>
    <strong class="line-pct">@if ($over)<span aria-hidden="true">⚠</span> @endif{{ $pct }}%</strong>
    <svg class="line-bar" viewBox="0 0 100 4" preserveAspectRatio="none" aria-hidden="true">
        <rect class="meter-track" x="0" y="0" width="100" height="4" />
        <rect class="meter-fill" x="0" y="0" width="{{ $fill }}" height="4" />
    </svg>
</a>
