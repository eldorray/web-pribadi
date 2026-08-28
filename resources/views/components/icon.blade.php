@props(['name' => '', 'size' => 20])
{{-- Inline Material Symbols glyph; paths live in App\Support\Icons. --}}
<svg {{ $attributes->merge(['class' => 'ms-icon']) }} width="{{ $size }}" height="{{ $size }}"
    viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true"
    focusable="false">{!! \App\Support\Icons::glyph($name) !!}</svg>
