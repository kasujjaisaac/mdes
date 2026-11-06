@props(['actions' => []])

<div class="page-actions">
    @foreach ($actions as $action)
        <a class="button button-primary" href="{{ $action['url'] }}">{{ $action['label'] }}</a>
    @endforeach
</div>
