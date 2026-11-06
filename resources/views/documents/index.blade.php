<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :actions="auth()->user()->can('documents.manage') ? [['label' => 'upload document', 'url' => route('documents.create'), 'style' => 'primary']] : []">
    <x-ui.card title="protected documents" subtitle="private storage with server-side download authorization">
        <x-ui.table :headers="['title', 'category', 'school', 'sensitivity', 'expiry', 'current version', '']">
            @forelse ($documents as $document)
                <tr>
                    <td><strong>{{ strtolower($document->title) }}</strong><p class="muted">{{ $document->notes }}</p></td>
                    <td>{{ strtolower($document->category->name) }}</td>
                    <td>{{ $document->school?->official_name ?? 'diocesan' }}</td>
                    <td><x-ui.badge>{{ $document->sensitivity }}</x-ui.badge></td>
                    <td>{{ $document->expiry_date?->toFormattedDateString() ?? '-' }}</td>
                    <td>{{ $document->currentVersion?->version ?? '-' }}</td>
                    <td><a class="table-action" href="{{ route('documents.download', $document) }}">download</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state title="no documents uploaded" message="protected school and diocesan files will appear here." /></td></tr>
            @endforelse
        </x-ui.table>
        <x-ui.pagination :paginator="$documents" />
    </x-ui.card>
</x-layouts.app>
