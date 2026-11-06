<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'analytics']]">
    <x-ui.card title="return status analytics" subtitle="dashboard source should use approved or locked data where official">
        <div class="stats-grid">
            @forelse ($returnsByStatus as $status => $count)
                <x-ui.stat-card :label="str_replace('_', ' ', $status)" :value="$count" meta="returns" tone="blue" />
            @empty
                <x-ui.empty-state title="no return data yet" message="analytics will populate from workflow records." />
            @endforelse
        </div>
    </x-ui.card>
</x-layouts.app>
