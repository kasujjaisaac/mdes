@props(['tone' => 'info', 'message' => null])

<div {{ $attributes->merge(['class' => 'alert alert-'.$tone]) }} role="status">
    {{ $message ?? $slot }}
</div>
