<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters>
<form class="registry-filters topbar-filters parish-topbar-filters" method="GET" action="{{ route('parishes.index') }}" role="search">

<label>Status<select name="status"><option value="">All parishes</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></label>
<button class="button parish-search-button" type="submit">Apply</button>
@can('diocesan-structure.manage')<a class="button button-primary" href="{{ route('parishes.create') }}"><x-ui.nav-icon name="hierarchy" /> Add parish</a>@endcan
</form></x-slot:topbarFilters>
<section class="card registry-records parish-directory">
@if(request()->filled('search') || request()->filled('status'))<div class="registry-results"><a href="{{ route('parishes.index') }}">Clear filters &times;</a></div>@endif
<p class="parish-scroll-hint" id="parish-table-hint">Swipe across to view schools, status and actions.</p><div class="table-wrap" role="region" aria-label="Parish directory" aria-describedby="parish-table-hint" tabindex="0"><table class="data-table registry-table"><thead><tr><th scope="col">No.</th><th scope="col">Parish</th><th scope="col">Code</th><th scope="col">Diocese</th><th scope="col">Schools</th><th scope="col">Status</th><th scope="col">Actions</th></tr></thead><tbody>
@forelse($parishes as $parish)<tr><td class="registry-row-number">{{ $parishes->firstItem()+$loop->index }}</td><td><strong class="registry-school-name">{{ $parish->name }}</strong></td><td><span class="parish-code">{{ $parish->code }}</span></td><td>{{ $parish->diocese?->name ?? 'Not recorded' }}</td><td><a class="table-action" href="{{ route('schools.index',['parish_id'=>$parish->id]) }}" aria-label="View {{ $parish->schools_count }} schools in {{ $parish->name }}"><strong>{{ $parish->schools_count }}</strong> {{ $parish->schools_count === 1 ? 'school' : 'schools' }}</a></td><td><span class="parish-status {{ $parish->is_active?'is-active':'is-inactive' }}">{{ $parish->is_active?'Active':'Inactive' }}</span></td><td>@can('diocesan-structure.manage')<div class="registry-row-actions"><a href="{{ route('parishes.edit',$parish) }}" aria-label="Edit {{ $parish->name }}">Edit</a></div>@endcan</td></tr>
@empty<tr><td colspan="7"><div class="registry-empty"><h2>No parishes found</h2><p>Adjust your search or add a parish.</p></div></td></tr>@endforelse
</tbody></table></div>@if($parishes->hasPages())<div class="native-pagination">{{ $parishes->links() }}</div>@endif</section>
</x-layouts.app>
