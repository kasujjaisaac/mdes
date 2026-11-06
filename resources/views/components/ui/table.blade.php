@props(['headers' => []])

<div class="table-wrap">
    <table class="data-table">
        @if ($headers)
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
