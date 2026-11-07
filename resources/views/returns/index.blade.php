<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><form class="registry-filters topbar-filters window-topbar-filters submission-topbar-filters" method="GET" action="{{ route('returns.index') }}" role="search">
<label class="registry-search">Find a school<div><input type="search" name="search" value="{{ request('search') }}" placeholder="School name"></div></label>
<label>Submission window<select name="period_id"><option value="">All windows</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(request('period_id')==$period->id)>{{ $period->year }} &middot; {{ $period->title }}</option>@endforeach</select></label>
<label>Status<select name="status"><option value="">All statuses</option>@foreach(['draft'=>'Draft','returned_for_correction'=>'Needs correction','locked'=>'Pending review','approved'=>'Approved','not_started'=>'Not started'] as $value=>$label)<option value="{{ $value }}" @selected(request('status')===$value)>{{ $label }}</option>@endforeach</select></label>
<button class="button" type="submit"><x-ui.nav-icon name="search"/> Search</button>@can('reporting-periods.view')<a class="button button-primary" href="{{ route('reporting-periods.index') }}"><x-ui.nav-icon name="calendar"/> Submission windows</a>@endcan
</form></x-slot:topbarFilters>
@if(auth()->user()->isSchoolRepresentative())
@if($startablePeriods->isNotEmpty())
<section class="submission-start-windows" aria-label="Available forms">
@foreach($startablePeriods as $period)
<article><div><span>Ready to start</span><h2>{{ $period->title }}</h2><p>Deadline: {{ ($period->extension_date ?? $period->closing_date)?->format('j M Y') ?? 'Not scheduled' }}</p></div><form method="POST" action="{{ route('returns.start', [$period, auth()->user()->school_id]) }}">@csrf<button class="button button-primary" type="submit"><x-ui.nav-icon name="clipboard"/> Start form</button></form></article>
@endforeach
</section>
@endif
<div class="mobile-only-layout">@include('returns.client-index')</div>
@endif
<div class="desktop-submission-layout">
<section class="card registry-records submission-records"><div class="registry-results"><span><strong>{{ $returns->total() }}</strong> {{ strtolower($title) }} @if($returns->total())<small>Showing {{ $returns->firstItem() }}&ndash;{{ $returns->lastItem() }}</small>@endif</span>@if(request()->filled('search') || request()->filled('period_id') || request()->filled('status'))<a href="{{ route('returns.index') }}">Clear filters &times;</a>@else<span class="registry-results-note">Current and historical school forms</span>@endif</div>
<div class="table-wrap"><table class="data-table window-table submission-table"><thead><tr><th>No.</th><th>School</th><th>Submission window</th><th>Status</th><th>Form sections</th><th>Submitted</th><th>Last updated</th><th>Actions</th></tr></thead><tbody>
@forelse($returns as $return)<tr><td data-label="No." class="registry-row-number">{{ $returns->firstItem()+$loop->index }}</td><td data-label="School"><a class="window-title" href="{{ route('schools.show',[$return->school,'return_id'=>$return->id]) }}">{{ $return->school->official_name }}</a><small>{{ $return->school->parish?->name ?? 'Parish not recorded' }}</small></td><td data-label="Collection"><span>{{ $return->reportingPeriod->title }}</span><small>{{ $return->reportingPeriod->year }} collection</small></td><td data-label="Status"><span class="submission-status is-{{ $return->status }}">{{ $return->statusLabel() }}</span></td><td data-label="Progress"><?php $saved=count(array_intersect_key($return->statistical_form??[], \App\Support\StatisticalForm::sections())); ?><div class="window-participation"><span>{{ $saved }} / 10 saved</span><progress max="10" value="{{ $saved }}">{{ $saved }}</progress></div></td><td data-label="Submitted">{{ $return->submitted_at?->format('d M Y') ?? 'Not submitted' }}</td><td data-label="Last updated">{{ $return->updated_at->format('d M Y') }}<small>{{ $return->updated_at->format('H:i') }}</small></td><td data-label="Action"><div class="submission-actions"><a class="window-open" href="{{ route('returns.show',$return) }}">{{ in_array($return->status,['draft','returned_for_correction']) ? 'Continue form' : 'View submission' }}</a></div></td></tr>
@empty<tr><td colspan="8"><div class="registry-empty"><x-ui.nav-icon name="clipboard"/><h2>No submissions found</h2><p>Adjust your filters or start a school form from a submission window.</p><a class="button" href="{{ route('returns.index') }}">Reset filters</a></div></td></tr>@endforelse
</tbody></table></div><div class="native-pagination">{{ $returns->links() }}</div></section>
</div>
</x-layouts.app>

