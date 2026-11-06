<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters>
<form class="registry-filters topbar-filters parish-topbar-filters" method="GET" action="{{ route('parishes.index') }}" role="search">
<label class="registry-search">Find a parish<div><input type="search" name="search" value="{{ request('search') }}" placeholder="Parish name or code"></div></label>
<label>Status<select name="status"><option value="">All parishes</option><option value="active" @selected(request('status')==='active')>Active</option><option value="inactive" @selected(request('status')==='inactive')>Inactive</option></select></label>
<button class="button parish-search-button" type="submit"><x-ui.nav-icon name="search" /> Search</button>
@can('diocesan-structure.manage')<a class="button button-primary" href="{{ route('parishes.create') }}"><x-ui.nav-icon name="hierarchy" /> Add parish</a>@endcan
</form></x-slot:topbarFilters>
<section class="card registry-records">
<div class="registry-results"><span><strong>{{ $parishes->total() }}</strong> parishes found</span>@if(request()->filled('search') || request()->filled('status'))<a href="{{ route('parishes.index') }}">Clear filters &times;</a>@endif</div>
<div class="table-wrap"><table class="data-table registry-table"><thead><tr><th>No.</th><th>Parish</th><th>Code</th><th>Diocese</th><th>Schools</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@forelse($parishes as $parish)<tr><td class="registry-row-number">{{ $parishes->firstItem()+$loop->index }}</td><td><strong class="registry-school-name">{{ $parish->name }}</strong></td><td>{{ $parish->code }}</td><td>{{ $parish->diocese?->name ?? 'Not recorded' }}</td><td><a class="table-action" href="{{ route('schools.index',['parish_id'=>$parish->id]) }}">{{ $parish->schools_count }} schools &rarr;</a></td><td><span class="registry-funding {{ $parish->is_active?'is-government':'' }}">{{ $parish->is_active?'Active':'Inactive' }}</span></td><td>@can('diocesan-structure.manage')<div class="registry-row-actions"><a href="{{ route('parishes.edit',$parish) }}">Edit</a></div>@endcan</td></tr>
@empty<tr><td colspan="7"><div class="registry-empty"><h2>No parishes found</h2><p>Adjust your search or add a parish.</p></div></td></tr>@endforelse
</tbody></table></div><div class="native-pagination">{{ $parishes->links() }}</div></section>
</x-layouts.app>
