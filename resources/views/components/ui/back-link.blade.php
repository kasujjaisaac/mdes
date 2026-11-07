@props(['href', 'label' => 'Back'])

<a {{ $attributes->class(['portal-back-link']) }} href="{{ $href }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m10 5-7 7 7 7M3 12h18"/></svg>
    <span>{{ $label }}</span>
</a>
