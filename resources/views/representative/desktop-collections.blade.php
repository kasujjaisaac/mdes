<section class="overview-panel overview-collections desktop-only-layout"><header><x-ui.nav-icon name="clipboard"/><h2>Collections</h2><a href="{{ route('returns.index') }}">View all returns &rarr;</a></header>
<div class="table-wrap"><table><thead><tr><th>Collection</th><th>Deadline</th><th>Status</th><th>Saved progress</th><th>Last updated</th><th>Action</th></tr></thead><tbody>
@forelse($periods as $period)
@php
    $entry=$period->schoolReturns->first();
    $total=count(\App\Support\StatisticalForm::sections());
    $saved=count(array_intersect_key($entry?->statistical_form ?? [],\App\Support\StatisticalForm::sections()));
@endphp
<tr><td><strong>{{ $period->title }}</strong><small>{{ $period->isSubmissionOpen() ? 'Window open' : 'Window closed' }}</small></td>
<td>{{ ($period->extension_date??$period->closing_date)?->format('d M Y') ?? 'Not scheduled' }}</td>
<td><span class="desktop-collection-status is-{{ $entry?->status ?? 'not_started' }}">{{ $entry?->status === 'returned_for_correction' ? 'Needs correction' : ($entry ? $entry->statusLabel() : 'Not started') }}</span></td>
<td><div class="desktop-collection-progress"><span>{{ $saved }} / {{ $total }} saved</span><progress max="{{ $total }}" value="{{ $saved }}" aria-label="Saved sections for {{ $period->title }}">{{ $saved }} / {{ $total }}</progress></div></td>
<td>@if($entry){{ $entry->updated_at->timezone('Africa/Kampala')->format('j M Y') }}<small>{{ $entry->updated_at->timezone('Africa/Kampala')->format('g:i A') }} EAT</small>@else<span>Not started</span>@endif</td>
<td>@include('representative.collection-action')</td></tr>
@empty<tr><td colspan="6">No collections have been assigned to your school.</td></tr>@endforelse
</tbody></table></div></section>
