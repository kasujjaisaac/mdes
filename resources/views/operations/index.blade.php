<x-layouts.app :title="$title" :breadcrumbs="[['label' => $title]]" :actions="$createRoute ? [['label' => 'new record', 'url' => $createRoute]] : []">
    <x-ui.card :title="$title" subtitle="operational records and workflow tracking">
        <x-ui.table :headers="$headers">
            @forelse ($records as $record)
                <tr>
                    @foreach ($rows($record) as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">
                        <x-ui.empty-state title="no records yet" message="records will appear here after users begin this workflow." />
                    </td>
                </tr>
            @endforelse
        </x-ui.table>
        <x-ui.pagination :paginator="$records" />
    </x-ui.card>
</x-layouts.app>
