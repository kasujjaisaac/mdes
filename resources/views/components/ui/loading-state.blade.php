@props(['message' => 'Loading'])

<div class="loading-state" aria-live="polite">
    <span class="loader" aria-hidden="true"></span>
    <span>{{ $message }}</span>
</div>
