<x-layouts.app :title="$title" :hide-heading="true">

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
@forelse($returns as $return)<tr><td data-label="No." class="registry-row-number">{{ $returns->firstItem()+$loop->index }}</td><td data-label="School"><a class="window-title" href="{{ route('representative.profile') }}">{{ $return->school->official_name }}</a><small>{{ $return->school->parish?->name ?? 'Parish not recorded' }}</small></td><td data-label="Collection"><span>{{ $return->reportingPeriod->title }}</span><small>{{ $return->reportingPeriod->year }} collection</small></td><td data-label="Status"><span class="submission-status is-{{ $return->status }}">{{ $return->statusLabel() }}</span></td><td data-label="Progress"><?php $saved=\App\Support\StatisticalForm::savedPageCount($return); ?><div class="window-participation"><span>{{ $saved }} / {{ count(\App\Support\StatisticalForm::sectionsForReturn($return))-1 }} saved</span><progress max="{{ count(\App\Support\StatisticalForm::sectionsForReturn($return))-1 }}" value="{{ $saved }}">{{ $saved }}</progress></div></td><td data-label="Submitted">{{ $return->submitted_at?->format('d M Y') ?? 'Not submitted' }}</td><td data-label="Last updated">{{ $return->updated_at->format('d M Y') }}<small>{{ $return->updated_at->format('H:i') }}</small></td><td data-label="Action"><div class="submission-actions"><a class="window-open" href="{{ route('returns.copy',$return) }}">Download PDF</a>@if($return->status==='not_started' && $return->reportingPeriod->allowsRepresentativeDraft())<form method="POST" action="{{ route('returns.start',[$return->reportingPeriod,$return->school_id]) }}">@csrf<button class="window-open" type="submit">Start form</button></form>@else<a class="window-open" href="{{ route('returns.show',$return) }}">{{ in_array($return->status,['draft','returned_for_correction']) ? 'Continue form' : 'View submission' }}</a>@endif</div></td></tr>
@empty<tr><td colspan="8"><div class="registry-empty"><x-ui.nav-icon name="clipboard"/><h2>No submissions found</h2><p>Adjust your filters or start a school form from a submission window.</p><a class="button" href="{{ route('returns.index') }}">Reset filters</a></div></td></tr>@endforelse
</tbody></table></div><div class="native-pagination">{{ $returns->links() }}</div></section>
</div>
</x-layouts.app>

