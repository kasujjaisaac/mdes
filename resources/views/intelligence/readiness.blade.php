<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'production readiness']]">
    <section class="stats-grid">
        <x-ui.stat-card label="classification" :value="$review->classification" meta="current review" tone="amber" />
        <x-ui.stat-card label="modules" :value="count($review->implemented_modules)" meta="implemented areas" tone="green" />
    </section>
    <div class="module-grid">
        <x-ui.card title="automated checks and scope" subtitle="readiness review summary">
            <div class="status-list">
                @foreach (array_merge($review->security_notes, $review->workflow_notes) as $note)
                    <div><span>{{ $note }}</span><x-ui.badge tone="success">checked</x-ui.badge></div>
                @endforeach
            </div>
        </x-ui.card>
        <x-ui.card title="remaining decisions" subtitle="must be settled before production">
            <div class="status-list">
                @foreach ($review->outstanding_decisions as $decision)
                    <div><span>{{ $decision }}</span><x-ui.badge tone="warning">pending</x-ui.badge></div>
                @endforeach
            </div>
        </x-ui.card>
    </div>
</x-layouts.app>
