@props(['id', 'title'])

<div class="modal" id="{{ $id }}" hidden>
    <div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <header>
            <h2 id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="icon-close" data-modal-close aria-label="Close">x</button>
        </header>
        {{ $slot }}
    </div>
</div>
