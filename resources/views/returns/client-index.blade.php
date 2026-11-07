
<div class="client-submissions">
    <header class="client-submissions-heading"><h1>My submissions</h1><p>Manage your school returns and follow their review status.</p></header>
    <div class="client-submission-summary" aria-label="All school submissions">
        <div><strong>{{ $submissionCounts->sum() }}</strong><span>Total returns</span></div>
        <div><strong>{{ ($submissionCounts['draft'] ?? 0) + ($submissionCounts['returned_for_correction'] ?? 0) + ($submissionCounts['not_started'] ?? 0) }}</strong><span>To complete</span></div>
        <div><strong>{{ collect(['submitted','certified','approved','locked'])->sum(fn($status) => $submissionCounts[$status] ?? 0) }}</strong><span>Submitted / locked</span></div>
    </div>
    <form class="client-submission-filters" method="GET" action="{{ route('returns.index') }}" aria-label="Filter submissions">
        <label for="client-period">Collection<select id="client-period" name="period_id"><option value="">All collections</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected(request('period_id') == $period->id)>{{ $period->title }}</option>@endforeach</select></label>
        <label for="client-status">Status<select id="client-status" name="status"><option value="">All statuses</option>@foreach(['draft'=>'Draft','returned_for_correction'=>'Needs correction','locked'=>'Submitted / locked','not_started'=>'Not started'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="button button-primary" type="submit">Apply filters</button>
        @if(request()->filled('period_id') || request()->filled('status'))<a class="client-filter-reset" href="{{ route('returns.index') }}">Clear filters</a>@endif
    </form>
    @if($errors->any())<x-ui.alert>{{ $errors->first() }}</x-ui.alert>@endif
    <div class="client-submission-list-heading"><h2>School returns</h2><span>{{ $returns->total() }} {{ Str::plural('return', $returns->total()) }}</span></div>
    <div class="client-submission-list">
    @forelse($returns as $return)
        @php
            $editable = in_array($return->status, ['draft','returned_for_correction','not_started']);
            $sectionCount = count(\App\Support\StatisticalForm::sections());
            $saved = count(array_intersect_key($return->statistical_form ?? [], \App\Support\StatisticalForm::sections()));
            $deadline = $return->reportingPeriod->extension_date ?? $return->reportingPeriod->closing_date;
        @endphp
        <article class="client-return-card">
            <header><span class="client-return-year"><x-ui.nav-icon name="clipboard"/>{{ $return->reportingPeriod->year }} COLLECTION</span><span class="client-return-status is-{{ $return->status }}">{{ $return->status === 'returned_for_correction' ? 'Needs correction' : $return->statusLabel() }}</span></header>
            <h3>{{ $return->reportingPeriod->title }}</h3>
            <dl><div><dt>Deadline</dt><dd>{{ $deadline?->format('j M Y') ?? 'Not scheduled' }}</dd></div><div><dt>Last updated</dt><dd>{{ $return->updated_at->timezone('Africa/Kampala')->format('j M Y') }}</dd></div></dl>
            @if($return->status === 'returned_for_correction' && $return->correction_reason)<p class="client-return-feedback"><strong>Review feedback</strong>{{ $return->correction_reason }}</p>@endif
            <div class="client-return-progress"><span>Sections saved <strong>{{ $saved }} / {{ $sectionCount }}</strong></span><progress max="{{ $sectionCount }}" value="{{ $saved }}" aria-label="Sections saved">{{ $saved }} of {{ $sectionCount }}</progress><small>Saved sections still need to be checked before submission.</small></div>
            <footer>@if($return->submitted_at)<span>Submitted {{ $return->submitted_at->timezone('Africa/Kampala')->format('j M Y') }}</span>@else<span>Not submitted yet</span>@endif<a class="button {{ $editable ? 'button-primary' : '' }}" href="{{ $editable && !$return->profile_confirmed_at ? route('representative.confirm', $return) : route('returns.show', $return) }}">{{ $return->status === 'returned_for_correction' ? 'Review corrections' : ($editable ? 'Continue form' : 'View submission') }} &rarr;</a></footer>
        </article>
    @empty
        <section class="client-submission-empty"><x-ui.nav-icon name="clipboard"/><h3>No returns found</h3><p>{{ $startablePeriods->isNotEmpty() ? 'Choose Start form above to begin your school return.' : (request()->filled('period_id') || request()->filled('status') ? 'Try another collection or clear your filters.' : 'No new forms are currently available. Assigned open windows will appear here.') }}</p>@if(request()->filled('period_id') || request()->filled('status'))<a class="button" href="{{ route('returns.index') }}">Clear filters</a>@endif</section>
    @endforelse
    </div>
    <div class="native-pagination">{{ $returns->links() }}</div>
</div>

