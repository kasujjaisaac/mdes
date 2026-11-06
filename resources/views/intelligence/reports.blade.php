<x-layouts.app :title="$title" :breadcrumbs="[['label' => 'reports']]">
    @if($errors->any())<x-ui.alert>{{ $errors->first() }}</x-ui.alert>@endif
    <section class="stats-grid">
        <x-ui.stat-card label="schools" :value="$schools" meta="registry records" tone="blue" />
        <x-ui.stat-card label="official returns" :value="$lockedReturns" meta="locked approved data" tone="green" />
    </section>
    <div class="module-grid">
        <x-ui.card title="generate export" subtitle="Downloadable files; statistical reports use locked returns.">
            <form class="resource-form" method="POST" action="{{ route('reports.export') }}">
                @csrf
                <div class="form-grid">
                    <label>report type<select name="report_type">@foreach (['school registry','enrolment','staff','examinations','infrastructure','religious affiliation','utilities','staff study programmes'] as $type)<option>{{ $type }}</option>@endforeach</select></label>
                    <label>format<select name="format">@foreach (['csv','xlsx','pdf'] as $format)<option>{{ $format }}</option>@endforeach</select></label>
                </div>
                <div class="form-actions"><button class="button button-primary">Generate report</button></div>
            </form>
        </x-ui.card>
        <x-ui.card title="recent exports" subtitle="audit-friendly export register">
            <x-ui.table :headers="['report', 'format', 'status', 'generated', 'download']">
                @forelse ($exports as $export)
                    <tr><td>{{ $export->report_type }}</td><td>{{ $export->format }}</td><td>{{ $export->path ? $export->status : ($export->status === 'generated' ? 'Legacy log (no file)' : $export->status) }}</td><td>{{ $export->generated_at->toDayDateTimeString() }}</td><td>@if($export->status === 'generated' && $export->path)<a href="{{ route('reports.download',$export) }}">Download</a>@elseif($export->status === 'failed'){{ $export->failure_reason }}@else &mdash; @endif</td></tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state title="no exports yet" message="export records appear after generation." /></td></tr>
                @endforelse
            </x-ui.table>
        </x-ui.card>
    </div>
</x-layouts.app>
