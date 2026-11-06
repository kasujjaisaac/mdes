<x-layouts.app :title="$title" :hide-heading="true">
<div class="announcement-workspace announcement-directory">@include('announcements.navigation', ['communicationsTitle' => 'Announcements', 'communicationsDescription' => 'Publish updates and track acknowledgement by each school.', 'communicationsTab' => 'announcements'])
<section class="announcement-panel"><div class="table-wrap"><table class="announcement-table announcement-list-table">@if($announcements->isNotEmpty())<thead><tr><th>Announcement</th><th>Audience</th><th>Schools acknowledged</th><th>Published</th></tr></thead>@endif<tbody>
@forelse($announcements as $announcement)
@php
$schoolIds = collect($announcement->target_filters['school_ids'] ?? []);
$deliveriesBySchool = $announcement->deliveries->groupBy('school_id');
$acknowledged = $announcement->deliveries->whereNotNull('read_at')->pluck('school_id')->unique()->count();
$total = $schoolIds->count();
@endphp
<tr class="announcement-list-row"><td class="announcement-title-cell"><strong>{{ $announcement->title }}</strong><small>By {{ $announcement->creator?->name ?? 'Previous admin' }}</small><details><summary>Read announcement</summary><p class="announcement-body">{{ $announcement->body }}</p></details></td>
<td data-label="Audience">{{ $total }} schools selected<small>{{ $deliveriesBySchool->count() }} schools reached</small>@if(!empty($announcement->target_filters['unreachable_school_ids']))<small>{{ count($announcement->target_filters['unreachable_school_ids']) }} without active accounts</small>@endif</td>
<td data-label="School acknowledgements"><strong>{{ $acknowledged }} / {{ $total }} schools</strong><small>{{ $total ? round($acknowledged / $total * 100) : 0 }}% acknowledged · {{ max(0,$total - $acknowledged) }} awaiting acknowledgement</small>
<details><summary>View school acknowledgements</summary><div class="announcement-school-status"><table><thead><tr><th>School</th><th>Status</th><th>Acknowledged on</th></tr></thead><tbody>
@foreach($schoolIds as $schoolId)
@php($schoolDeliveries = $deliveriesBySchool->get($schoolId, collect()))
@php($readAt = $schoolDeliveries->whereNotNull('read_at')->sortBy('read_at')->first()?->read_at)
<tr><td>{{ $audienceSchools->get($schoolId)?->official_name ?? 'School no longer registered' }}</td><td class="{{ $readAt ? 'acknowledged' : '' }}">{{ $readAt ? 'Acknowledged' : ($schoolDeliveries->isEmpty() ? 'No active account at publication' : 'Not acknowledged') }}</td><td>{{ $readAt?->timezone('Africa/Kampala')->format('d M Y, H:i') ?? '—' }}</td></tr>
@endforeach
</tbody></table></div></details></td><td data-label="Published">{{ $announcement->published_at?->timezone('Africa/Kampala')->format('d M Y, H:i') ?? 'Not published' }}</td></tr>
@empty<tr class="announcement-empty-row"><td colspan="4" class="announcement-empty"><div class="announcement-directory-empty"><span class="announcement-empty-icon"><x-ui.nav-icon name="bell"/></span><h2>Your first announcement starts here</h2><p>Share an update with one school or a selected group, then track their acknowledgements.</p><a class="button button-primary" href="{{ route('announcements.create') }}">Create announcement</a></div></td></tr>@endforelse
</tbody></table></div><div class="announcement-pagination">{{ $announcements->links() }}</div></section></div>
</x-layouts.app>
