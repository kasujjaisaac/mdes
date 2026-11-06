<x-layouts.app :title="$title" :hide-heading="true">
<div class="submission-review-workspace">
<header class="review-queue-heading"><div><span>School reporting</span><h1>School Submissions</h1><p>Review submitted forms, approve them or request corrections.</p></div></header>
@php($pending = collect(\App\Models\SchoolReturn::PENDING_REVIEW_STATUSES)->sum(fn($status) => $submissionCounts[$status] ?? 0))
<div class="review-queue-stats">
@foreach([''=>'Total submitted','pending_review'=>'Pending review','approved'=>'Approved','returned_for_correction'=>'Returned for correction'] as $status=>$label)
<a href="{{ route('returns.index',array_merge(request()->except('page','status'),$status ? ['status'=>$status] : [])) }}" class="review-stat {{ request('status','') === $status ? 'is-selected' : '' }}"><span>{{ $label }}</span><strong>{{ $status === '' ? $submissionCounts->sum() : ($status === 'pending_review' ? $pending : ($submissionCounts[$status] ?? 0)) }}</strong></a>
@endforeach
</div>
<form class="review-queue-filters" method="GET" action="{{ route('returns.index') }}">
@if(request('period_id'))<input type="hidden" name="period_id" value="{{ request('period_id') }}">@endif
<label class="review-school-search">School<input type="search" name="search" placeholder="Find a school" value="{{ request('search') }}"></label>
<label>Year<select name="year"><option value="">All years</option>@foreach($years as $year)<option value="{{ $year }}" @selected(request('year') == $year)>{{ $year }}</option>@endforeach</select></label>
<label>Parish<select name="parish_id"><option value="">All parishes</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" @selected(request('parish_id') == $parish->id)>{{ $parish->name }}</option>@endforeach</select></label>
<label>Level<select name="level"><option value="">All levels</option>@foreach(\App\Services\AnnouncementAudienceService::LEVELS as $value=>$label)<option value="{{ $value }}" @selected(request('level') === $value)>{{ $label }}</option>@endforeach</select></label>
<label>Review status<select name="status"><option value="">All statuses</option>@foreach(['pending_review'=>'Pending review','approved'=>'Approved','returned_for_correction'=>'Returned for correction'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value || ($value === 'pending_review' && in_array(request('status'),\App\Models\SchoolReturn::PENDING_REVIEW_STATUSES)))>{{ $label }}</option>@endforeach</select></label>
<div class="review-filter-actions"><a href="{{ route('returns.index') }}">Reset</a><button class="button button-primary" type="submit">Apply filters</button></div>
</form>
@if($errors->any())<div class="announcement-error" role="alert">{{ $errors->first() }}</div>@endif
<section class="review-queue-records"><header><strong>{{ $returns->total() }} submissions</strong><span>School answers are read-only</span></header>
<div class="table-wrap"><table class="review-queue-table"><thead><tr><th>No.</th><th>School</th><th>Parish</th><th>Level</th><th>Submitted</th><th>Review status</th><th>Actions</th></tr></thead><tbody>
@forelse($returns as $return)
<tr><td class="review-row-number">{{ $returns->firstItem()+$loop->index }}</td><td data-label="School"><a class="review-school-name" href="{{ route('returns.show',$return) }}">{{ $return->school->official_name }}</a><small>{{ $return->reportingPeriod->year }} reporting</small><div class="submission-mobile-table-details"><span>{{ $return->school->parish?->name ?? 'Parish not recorded' }} &middot; {{ \App\Services\AnnouncementAudienceService::LEVELS[$return->school->school_level] ?? $return->school->school_level }}</span><time>{{ $return->submitted_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</time></div></td><td data-label="Parish">{{ $return->school->parish?->name ?? 'Not recorded' }}</td><td data-label="Level">{{ \App\Services\AnnouncementAudienceService::LEVELS[$return->school->school_level] ?? $return->school->school_level }}</td><td data-label="Submitted">{{ $return->submitted_at->timezone('Africa/Kampala')->format('d M Y') }}<small>{{ $return->submitted_at->timezone('Africa/Kampala')->format('H:i') }}</small></td><td data-label="Review status"><span class="review-status is-{{ $return->status }}">{{ $return->statusLabel() }}</span></td><td data-label="Actions"><div class="review-row-actions"><a class="review-action" href="{{ route('returns.show',$return) }}">View</a></div></td></tr>
@empty<tr><td colspan="7"><div class="review-queue-empty"><x-ui.nav-icon name="clipboard"/><h2>No submissions found</h2><p>Submitted school forms will appear here. Try adjusting your filters.</p></div></td></tr>@endforelse
</tbody></table></div><div class="native-pagination">{{ $returns->links() }}</div></section>

</div>
</x-layouts.app>
