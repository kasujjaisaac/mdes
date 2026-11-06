<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs">
    <section class="stats-grid">
        @foreach ($stats as $stat)
            <x-ui.stat-card :label="$stat['label']" :value="$stat['value']" meta="configured" tone="blue" />
        @endforeach
    </section>

    <x-ui.card title="system configuration" subtitle="administrative overview">
        <div class="status-list">
            <div><span>authentication</span><x-ui.badge tone="success">active users only</x-ui.badge></div>
            <div><span>authorization</span><x-ui.badge tone="success">role and permission gates</x-ui.badge></div>
            <div><span>documents</span><x-ui.badge tone="success">private storage</x-ui.badge></div>
            <div><span>audit</span><x-ui.badge tone="success">append-only records</x-ui.badge></div>
        </div>
    </x-ui.card>
</x-layouts.app>
