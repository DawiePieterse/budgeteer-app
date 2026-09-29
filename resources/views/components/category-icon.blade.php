@props(['icon', 'size' => 24])
<svg {{ $attributes->merge(['class' => 'category-icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ \App\Support\CategoryIcons::path($icon) }}"/></svg>
