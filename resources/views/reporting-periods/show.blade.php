<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions">@can('reporting-periods.manage')@unless($period->is_archived)<a class="button" href="{{ route('reporting-periods.edit',$period) }}">Edit schedule</a>@endunless @endcan</div></x-slot:topbarFilters>
<div class="window-detail"><header class="window-detail-heading"><div><a href="{{ route('reporting-periods.index') }}">&larr; Submission Windows</a><h1>{{ $period->title }}</h1><p>{{ $period->year }} collection &middot; {{ ucfirst(str_replace('_',' ',$period->type)) }}</p></div><span class="window-status is-{{ $period->status }}">{{ ucfirst($period->status) }}</span></header>
    <section class="stats-grid">
        <x-ui.stat-card label="schools" :value="$period->schools->count()" meta="selected participants" tone="blue" />
        <x-ui.stat-card label="draft" :value="$statusCounts->get('draft', 0)" meta="in progress" tone="amber" />
        <x-ui.stat-card label="submitted" :value="$statusCounts->get('submitted', 0)" meta="awaiting review" tone="green" />
        <x-ui.stat-card label="locked" :value="$statusCounts->get('locked', 0)" meta="official data" tone="red" />
    </section>
    <div class="window-detail-grid">
        <x-ui.card title="participating schools" subtitle="start or inspect returns">
            <x-ui.table :headers="['school', 'parish', 'status', '']">
                @foreach ($period->schools as $school)
                    @php($return = $returns->firstWhere('school_id', $school->id))
                    <tr>
                        <td>{{ $school->official_name }}</td>
                        <td>{{ $school->parish->name }}</td>
                        <td><x-ui.badge>{{ $return?->status ?? 'not_started' }}</x-ui.badge></td>
                        <td>
                            @if ($return)
                                <a class="table-action" href="{{ route('returns.show', $return) }}">open</a>
                            @else
                                <form method="POST" action="{{ route('returns.start', [$period, $school]) }}">@csrf<button class="table-action button-link" type="submit">start</button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>
        </x-ui.card>
        <x-ui.card title="period details" subtitle="workflow dates and instructions">
            <div class="detail-list">
                <div><span>type</span><strong>{{ str_replace('_', ' ', $period->type) }}</strong></div>
                <div><span>year</span><strong>{{ $period->year }} {{ $period->term }}</strong></div>
                <div><span>opens</span><strong>{{ $period->opening_date->format('Y-m-d') }}</strong></div>
                <div><span>closes</span><strong>{{ $period->closing_date->format('Y-m-d') }}</strong></div>
            </div>
            <p class="lede">{{ $period->instructions ?? 'no instructions recorded.' }}</p>
        </x-ui.card>
    </div>
</div></x-layouts.app>
