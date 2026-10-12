@props(['now', 'last', 'budget', 'from', 'days', 'lastLabel', 'nowLabel'])
@php
    // Running totals day by day: this month (blue, with a light wash under it), last month (grey), and the
    // even pace from nothing to the whole budget (dashed). Hover or focus on a day shows all three.
    $w = 360; $h = 200; $left = 40; $right = $w - 8; $top = 8; $bottom = $h - 24;
    $axis = \App\Support\Charts::axis((int) max([1, $budget, ...$now, ...$last]), 3);
    $step = ($right - $left) / max(1, $days - 1);
    $x = fn (int $i) => round($left + $i * $step, 2);
    $y = fn (int $cents) => round($bottom - ($bottom - $top) * max(0, $cents) / $axis['max'], 2);
    $pace = fn (int $i) => $days > 1 ? (int) round($budget * ($i + 1) / $days) : $budget;
    $ticks = array_unique([0, (int) round(($days - 1) / 3), (int) round(2 * ($days - 1) / 3), $days - 1]);
    $endX = $now !== [] ? $x(count($now) - 1) : $left;
@endphp
<svg {{ $attributes->class(['chart', 'chart-pace']) }} viewBox="0 0 {{ $w }} {{ $h }}" role="group" aria-label="Spending this month day by day, against last month and the even pace">
    @foreach ($axis['ticks'] as $tick)
        <line class="chart-grid" x1="{{ $left }}" x2="{{ $right }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}"/>
        <text class="chart-tick" x="{{ $left - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end">{{ \App\Support\Charts::short($tick) }}</text>
    @endforeach
    @foreach ($ticks as $i)
        <text class="chart-tick" x="{{ $x($i) }}" y="{{ $h - 6 }}" text-anchor="{{ $loop->first ? 'start' : ($loop->last ? 'end' : 'middle') }}">{{ $from->addDays($i)->format('j M') }}</text>
    @endforeach
    @if ($budget > 0)
        <line class="chart-threshold" x1="{{ $left }}" y1="{{ $bottom }}" x2="{{ $right }}" y2="{{ $y($budget) }}"/>
    @endif
    @if (count($last) > 1)
        <polyline class="chart-line last" points="{{ \App\Support\Charts::points($last, $left, $x(count($last) - 1), $top, $bottom, $axis['max']) }}"/>
    @endif
    @if ($now !== [])
        <polygon class="chart-area" points="{{ $left }},{{ $bottom }} {{ \App\Support\Charts::points($now, $left, $endX, $top, $bottom, $axis['max']) }} {{ $endX }},{{ $bottom }}"/>
        @if (count($now) > 1)
            <polyline class="chart-line out" points="{{ \App\Support\Charts::points($now, $left, $endX, $top, $bottom, $axis['max']) }}"/>
        @endif
        <circle class="chart-dot out" cx="{{ $endX }}" cy="{{ $y(end($now)) }}" r="4"/>
    @endif
    <line class="chart-crosshair" x1="0" x2="0" y1="{{ $top }}" y2="{{ $bottom }}"/>
    @for ($i = 0; $i < $days; $i++)
        <rect class="chart-hit" x="{{ round($x($i) - $step / 2, 2) }}" y="{{ $top }}" width="{{ round($step, 2) }}" height="{{ $bottom - $top }}" data-x="{{ $x($i) }}"
            tabindex="{{ $i === count($now) - 1 ? 0 : -1 }}" role="img" aria-label="{{ $from->addDays($i)->format('j M') }}"
            data-tip-title="{{ $from->addDays($i)->format('j M') }}"
            data-tip="{{ json_encode(array_values(array_filter([
                isset($now[$i]) ? [$nowLabel, rand_whole($now[$i]), 'k-out'] : null,
                isset($last[$i]) ? [$lastLabel, rand_whole($last[$i]), 'k-last'] : null,
                $budget > 0 ? ['Even pace', rand_whole($pace($i)), 'k-budget'] : null,
            ]))) }}"/>
    @endfor
</svg>
