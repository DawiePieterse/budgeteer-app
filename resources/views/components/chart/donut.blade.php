@props(['segments', 'total', 'caption' => 'spent'])
@php
    // Shares of a whole as arcs round a ring, with a 2px gap of the card's colour between them; the total in the middle.
    $r = 60; $c = 2 * M_PI * $r; $sum = max(1, array_sum(array_column($segments, 'cents')));
    $offset = 0.0;
    $arcs = [];
    foreach ($segments as $s) {
        $length = $c * $s['cents'] / $sum;
        $arcs[] = $s + ['length' => max(0.5, $length - (count($segments) > 1 ? 2 : 0)), 'offset' => $offset, 'pct' => (int) round($s['cents'] / $sum * 100)];
        $offset += $length;
    }
@endphp
<svg {{ $attributes->class(['chart', 'chart-donut']) }} viewBox="0 0 150 150" role="group" aria-label="Spending by group">
    <circle class="chart-donut-track" cx="75" cy="75" r="{{ $r }}"/>
    @foreach ($arcs as $a)
        <circle @class(['chart-arc', 'group-colour-'.$a['colour'] => $a['colour'] !== null]) cx="75" cy="75" r="{{ $r }}"
            stroke-dasharray="{{ round($a['length'], 2) }} {{ round($c, 2) }}" stroke-dashoffset="{{ round(-$a['offset'], 2) }}" transform="rotate(-90 75 75)"
            tabindex="0" role="img" aria-label="{{ $a['name'] }}: {{ money($a['cents']) }}, {{ $a['pct'] }}%"
            data-tip-title="{{ $a['name'] }}" data-tip="{{ json_encode([[$a['pct'].'% of spending', rand_whole($a['cents']), 'k-group']]) }}"/>
    @endforeach
    <text class="chart-donut-value" x="75" y="74" text-anchor="middle">{{ rand_whole($total) }}</text>
    <text class="chart-tick" x="75" y="92" text-anchor="middle">{{ $caption }}</text>
</svg>
