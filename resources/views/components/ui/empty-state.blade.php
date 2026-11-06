@props(['title', 'message', 'actionLabel' => null, 'actionUrl' => null])

<div class="empty-state">
    <div class="empty-icon" aria-hidden="true">-</div>
    <h2>{{ $title }}</h2>
    <p>{{ $message }}</p>
    @if ($actionLabel && $actionUrl)
        <a class="button button-secondary" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    @endif
</div>
