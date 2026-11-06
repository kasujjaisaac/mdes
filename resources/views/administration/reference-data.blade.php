<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <div class="module-grid">
        @foreach ($groups as $name => $records)
            <x-ui.card :title="$name" subtitle="controlled values">
                <x-ui.table :headers="['name', 'code', 'status']">
                    @forelse ($records as $record)
                        <tr>
                            <td>{{ $record->name }}</td>
                            <td>{{ $record->code ?? $record->slug ?? '-' }}</td>
                            <td><x-ui.badge :tone="$record->is_active ? 'success' : 'warning'">{{ $record->is_active ? 'active' : 'inactive' }}</x-ui.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">no controlled values registered</td></tr>
                    @endforelse
                </x-ui.table>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
