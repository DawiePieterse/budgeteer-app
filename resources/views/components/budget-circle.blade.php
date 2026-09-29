@props(['spent', 'budget' => null, 'label', 'icon', 'href' => null, 'id'])
@php
    // The circle fills from the bottom with the share of the budget used; over budget it is full and red.
    $hasBudget = $budget !== null && $budget > 0;
    $over = $hasBudget && $spent > $budget;
    $share = $hasBudget ? min(1, max(0, $spent / $budget)) : 0;
    $size = 56;
    $r = 26;
    $level = round($size - 2 - $share * ($size - 4), 1); // y of the fill's top edge, inside the ring
    $summary = $hasBudget
        ? $label.': '.money($spent).' of '.money($budget).' spent, '.($over ? money($spent - $budget).' over' : money($budget - $spent).' left')
        : $label.': '.money($spent).' spent, not on a budget line';
@endphp
<{{ $href ? 'a' : 'div' }} {{ $attributes->class(['circle', 'is-over' => $over, 'is-empty' => $spent <= 0, 'no-budget' => ! $hasBudget]) }} @if ($href) href="{{ $href }}" @endif aria-label="{{ $summary }}" title="{{ $summary }}">
    <span class="circle-name">{{ $label }}</span>
    <span class="circle-spent">{{ rand_whole($spent) }}</span>
    <svg class="circle-art" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}" aria-hidden="true" focusable="false">
        <defs><clipPath id="clip-{{ $id }}"><circle cx="28" cy="28" r="{{ $r }}"/></clipPath></defs>
        <circle class="circle-track" cx="28" cy="28" r="{{ $r }}"/>
        @if ($share > 0)
            <g clip-path="url(#clip-{{ $id }})">
                <rect class="circle-fill" x="0" y="{{ $level }}" width="{{ $size }}" height="{{ $size }}"/>
                @if ($share < 1)<line class="circle-level" x1="0" y1="{{ $level }}" x2="{{ $size }}" y2="{{ $level }}"/>@endif
            </g>
        @endif
        <circle class="circle-ring" cx="28" cy="28" r="{{ $r }}" fill="none"/>
        <g transform="translate(16 16)"><x-category-icon :icon="$icon" /></g>
    </svg>
    @if (! $hasBudget)
        <span class="circle-left"><span class="circle-note">no budget</span></span>
    @else
        <span class="circle-left">{{ rand_whole(abs($budget - $spent)) }}<span class="circle-note">@if ($over)<span aria-hidden="true">⚠</span> over @else left @endif</span></span>
    @endif
</{{ $href ? 'a' : 'div' }}>
