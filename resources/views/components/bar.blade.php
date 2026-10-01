@props(['share', 'over' => false])
@php
    // A bar the width of its container: the track is the whole (100%), the fill the share of it. Drawn in SVG
    // because the page's security policy allows no inline styles.
    $fill = round(min(1, max(0, $share)) * 100, 1);
    $fill = $share > 0 ? max(1, $fill) : 0;
@endphp
<svg {{ $attributes->class(['bar', 'is-over' => $over]) }} viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true" focusable="false"><rect class="bar-track" width="100" height="10"/>@if ($fill > 0)<rect class="bar-fill" width="{{ $fill }}" height="10"/>@endif</svg>
