<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :actions="$actions">
    <section class="stats-grid" aria-label="diocesan structure summary">
        <x-ui.stat-card label="parishes" :value="$totals['parishes']" meta="registered catholic parishes" tone="blue" />
        <x-ui.stat-card label="schools" :value="$totals['schools']" meta="schools attached to parishes" tone="green" />
        <x-ui.stat-card label="active assignments" :value="$totals['active_assignments']" meta="coordinators and inspectors" tone="amber" />
        <x-ui.stat-card label="unassigned parishes" :value="$totals['unassigned_parishes']" meta="need portfolio assignment" tone="red" />
    </section>

    <div class="module-grid">
        <x-ui.card title="parishes" subtitle="ecclesiastical structure used for jurisdiction and school filtering">
            <x-ui.table :headers="['parish', 'code', 'diocese', 'schools', 'status', '']">
                @forelse ($parishes as $parish)
                    <tr>
                        <td>{{ $parish->name }}</td>
                        <td>{{ $parish->code }}</td>
                        <td>{{ $parish->diocese->name }}</td>
                        <td>{{ $parish->schools_count }}</td>
                        <td><x-ui.badge :tone="$parish->is_active ? 'success' : 'warning'">{{ $parish->is_active ? 'active' : 'inactive' }}</x-ui.badge></td>
                        <td>
                            @can('diocesan-structure.manage')
                                <a class="table-action" href="{{ route('diocesan-structure.parishes.edit', $parish) }}">edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state title="no parishes" message="create parishes before registering schools." /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>

        <x-ui.card title="jurisdiction assignments" subtitle="effective-dated portfolio responsibilities">
            <div class="permission-list">
                @forelse ($assignments as $assignment)
                    <div>
                        <strong>{{ $assignment->user->name }}</strong>
                        <span>{{ str_replace('_', ' ', $assignment->assignment_type) }} - {{ $assignment->parish?->name ?? $assignment->educationCluster?->name ?? 'unscoped' }}</span>
                        <span>{{ $assignment->effective_from->format('Y-m-d') }} to {{ $assignment->effective_to?->format('Y-m-d') ?? 'current' }}</span>
                    </div>
                @empty
                    <x-ui.empty-state title="no assignments" message="assign coordinators and inspectors to parishes or clusters." />
                @endforelse
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
