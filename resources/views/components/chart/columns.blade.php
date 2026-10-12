@props(['months', 'budget'])
@php
    // Spending per budget month as columns, money in as a line, the budget as a dashed threshold; one axis in rand.
    // A month more than 3% over the budget is red; the month still running is pale.
    $w = 720; $h = 250; $left = 44; $right = $w - 8; $top = 10; $bottom = $h - 26;
    $axis = \App\Support\Charts::axis((int) max([1, $budget, ...array_column($months, 'spent'), ...array_column($months, 'received')]));
    $y = fn (int $cents) => round($bottom - ($bottom - $top) * max(0, $cents) / $axis['max'], 2);
    $slot = ($right - $left) / max(1, count($months));
    $barWidth = min(24, $slot * 0.5);
    $labelEvery = (int) ceil(count($months) / 12);
    $x = fn (int $i) => round($left + $slot * $i + $slot / 2, 2); // the middle of month $i
@endphp
<svg {{ $attributes->class(['chart', 'chart-columns']) }} viewBox="0 0 {{ $w }} {{ $h }}" role="group" aria-label="Spending, money in and the budget per month">
    @foreach ($axis['ticks'] as $tick)
        <line class="chart-grid" x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}"/>
        <text class="chart-tick" x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end">{{ \App\Support\Charts::short($tick) }}</text>
    @endforeach
    @foreach ($months as $i => $m)
        <path @class(['chart-col', 'is-over' => $budget > 0 && $m['spent'] > $budget * 1.03, 'is-running' => $m['running']]) d="{{ \App\Support\Charts::barPath($x($i) - $barWidth / 2, $y($m['spent']), $barWidth, $bottom - $y($m['spent'])) }}"/>
        @if ($i % $labelEvery === 0 || $loop->last)
            <text @class(['chart-tick', 'is-strong' => $loop->last]) x="{{ $x($i) }}" y="{{ $h - 8 }}" text-anchor="middle">{{ $m['label'] }}</text>
        @endif
    @endforeach
    @if ($budget > 0)
        <line class="chart-threshold" x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y($budget) }}" y2="{{ $y($budget) }}"/>
    @endif
    @if (count($months) > 1)
        <polyline class="chart-line in" points="{{ \App\Support\Charts::points(array_column($months, 'received'), $left + $slot / 2, $right - $slot / 2, $top, $bottom, $axis['max']) }}"/>
    @endif
    @foreach ($months as $i => $m)
        <circle class="chart-dot in" cx="{{ $x($i) }}" cy="{{ $y($m['received']) }}" r="4"/>
    @endforeach
    {{-- One hit target per month, the whole height of the chart: hover or focus shows the month's numbers, a click its transactions. --}}
    @foreach ($months as $i => $m)
        <a href="{{ $m['href'] }}" class="chart-hit" aria-label="{{ $m['title'] }}: spent {{ money($m['spent']) }}, money in {{ money($m['received']) }}@if ($budget > 0), budget {{ money($budget) }}@endif"
            data-tip-title="{{ $m['title'] }}{{ $m['running'] ? ' so far' : '' }}"
            data-tip="{{ json_encode([['Spent', rand_whole($m['spent']), 'k-out'], ['Money in', rand_whole($m['received']), 'k-in'], ...($budget > 0 ? [['Budget', rand_whole($budget), 'k-budget']] : [])]) }}">
            <rect x="{{ round($left + $slot * $i, 2) }}" y="{{ $top }}" width="{{ round($slot, 2) }}" height="{{ $bottom - $top }}"/>
        </a>
    @endforeach
</svg>
