@props(['spent', 'budget', 'label', 'href' => null, 'icon' => null])
@php
    // The bar's full width is the budget (100%); the fill is what has been spent so far.
    $pct = $budget > 0 ? (int) round($spent / $budget * 100) : ($spent > 0 ? 100 : 0);
    $over = $spent > $budget;
    $summary = $label.': '.money($spent).' of '.money($budget).' ('.$pct.'%)'.($over ? ', '.money($spent - $budget).' over' : ', '.money($budget - $spent).' left');
@endphp
<{{ $href ? 'a' : 'div' }} {{ $attributes->class(['line', 'is-over' => $over]) }} @if ($href) href="{{ $href }}" @endif aria-label="{{ $summary }}">
    <span class="line-head">
        @if ($icon)<x-category-icon :icon="$icon" :size="20" />@endif
        <span class="line-name">{{ $label }}</span>
        <span @class(['line-left', 'over' => $over])>{{ $over ? money($spent - $budget).' over' : money($budget - $spent).' left' }}</span>
    </span>
    <x-bar :share="$budget > 0 ? $spent / $budget : ($spent > 0 ? 1 : 0)" :over="$over" />
    <span class="line-foot"><span>{{ money($spent) }} of {{ money($budget) }}</span><strong class="line-pct">{{ $pct }}%</strong></span>
</{{ $href ? 'a' : 'div' }}>
