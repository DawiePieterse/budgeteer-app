@props(['values', 'label' => null, 'width' => 96, 'height' => 28])
@php
    // A small line of recent months in a quiet grey, the latest month as a dot in the accent colour (set by a class).
    $min = $values === [] ? 0 : min($values); $max = $values === [] ? 0 : max($values);
    $x = fn (int $i) => round(count($values) > 1 ? 3 + $i * ($width - 6) / (count($values) - 1) : $width / 2, 2);
    $y = fn (int $v) => round($height - 4 - ($max > $min ? ($v - $min) / ($max - $min) : 0.5) * ($height - 8), 2);
@endphp
@if (count($values) > 1)
    <svg {{ $attributes->class(['chart-spark']) }} width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}" @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif focusable="false">
        <polyline points="{{ implode(' ', array_map(fn ($v, $i) => $x($i).','.$y($v), $values, array_keys($values))) }}"/>
        <circle cx="{{ $x(count($values) - 1) }}" cy="{{ $y(end($values)) }}" r="3"/>
    </svg>
@endif
