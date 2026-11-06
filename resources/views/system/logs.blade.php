<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <div class="module-grid">
        <x-ui.card title="application log" subtitle="latest entries">
            <div class="log-panel">
                @forelse ($lines as $line)
                    <pre>{{ $line }}</pre>
                @empty
                    <p class="muted">no application log entries found</p>
                @endforelse
            </div>
        </x-ui.card>

        <x-ui.card title="audit events" subtitle="recent sensitive actions">
            <x-ui.table :headers="['action', 'entity', 'reason', 'ip']">
                @forelse ($auditEvents as $event)
                    <tr>
                        <td>{{ $event->action }}</td>
                        <td>{{ class_basename($event->auditable_type) }} #{{ $event->auditable_id }}</td>
                        <td>{{ $event->reason ?? '-' }}</td>
                        <td>{{ $event->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">no audit events recorded</td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    </div>
</x-layouts.app>
