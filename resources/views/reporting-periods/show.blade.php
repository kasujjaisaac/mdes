<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions">@can('reporting-periods.manage')@unless($period->is_archived)<a class="button" href="{{ route('reporting-periods.edit',$period) }}">Edit schedule</a>@endunless @endcan</div></x-slot:topbarFilters>
<div class="window-detail"><header class="window-detail-heading"><div><a href="{{ route('reporting-periods.index') }}">&larr; Submission Windows</a><h1>{{ $period->title }}</h1><p>{{ $period->year }} collection &middot; {{ ucfirst(str_replace('_',' ',$period->type)) }}</p></div><span class="window-status is-{{ $period->status }}">{{ ucfirst($period->status) }}</span></header>
    <section class="stats-grid">
        <x-ui.stat-card label="schools" :value="$period->schools->count()" meta="selected participants" tone="blue" />
        <x-ui.stat-card label="pending review" :value="collect(\App\Models\SchoolReturn::PENDING_REVIEW_STATUSES)->sum(fn($status)=>$statusCounts->get($status,0))" meta="submitted school forms" tone="amber" />
        <x-ui.stat-card label="approved" :value="$statusCounts->get('approved', 0)" meta="review completed" tone="green" />
        <x-ui.stat-card label="returned for correction" :value="$statusCounts->get('returned_for_correction', 0)" meta="awaiting school corrections" tone="red" />
    </section>
    <div class="window-detail-grid">
        <x-ui.card title="participating schools" subtitle="track submitted school forms">
            <x-ui.table :headers="['school', 'parish', 'status', '']">
                @foreach ($period->schools as $school)
                    @php($return = $returns->firstWhere('school_id', $school->id))
                    <tr>
                        <td>{{ $school->official_name }}</td>
                        <td>{{ $school->parish->name }}</td>
                        <td><x-ui.badge>{{ $return?->submitted_at ? $return->statusLabel() : 'Not submitted' }}</x-ui.badge></td>
                        <td>
                            @if ($return && ($return->submitted_at || auth()->user()->isSchoolRepresentative()))
                                <a class="table-action" href="{{ route('returns.show', $return) }}">View</a>
                            @elseif(auth()->user()->isSchoolRepresentative() && $period->allowsRepresentativeDraft())
                                <form method="POST" action="{{ route('returns.start', [$period, $school]) }}">@csrf<button class="table-action button-link" type="submit">Start form</button></form>
                            @else
                                <span class="user-directory-muted">Awaiting school</span>
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
