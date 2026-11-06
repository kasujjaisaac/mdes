@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || $subtitle)
        <div class="card-header">
            @if ($title)
                <h2>{{ $title }}</h2>
            @endif
            @if ($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
    @endif

    {{ $slot }}
</section>
