<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="school-overview-toolbar"><div class="client-welcome-panel"><span class="client-avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><h2>Welcome back, {{ auth()->user()->name }}</h2><p><x-ui.nav-icon name="school"/>{{ $school?->official_name ?? 'No school assigned' }}</p></div></div><div class="school-overview-identity"><span>School overview</span><h1>{{ $school?->official_name ?? 'No school assigned' }}</h1></div><div class="desktop-only-layout">@if($statisticReturns->isNotEmpty())<form method="GET"><label for="overview-source">Statistics snapshot</label><select id="overview-source" name="stats_return_id" onchange="this.form.requestSubmit()">@foreach($statisticReturns as $source)<option value="{{ $source->id }}" @selected($statisticReturn?->id===$source->id)>{{ $source->reportingPeriod?->title }}</option>@endforeach</select><noscript><button class="button">View</button></noscript><span class="overview-snapshot-status"><x-ui.nav-icon name="shield"/>{{ ucfirst(str_replace('_',' ',$statisticReturn->status)) }}</span></form>@endif</div></div></x-slot:topbarFilters>
<div class="school-overview-dashboard">
@if(!$school)<x-ui.alert>Please contact the administrator to assign your school.</x-ui.alert>@else
<?php
 $submittedStates=['submitted','certified','approved','locked'];
 $nextWindow=$periods->first(fn($p)=>$p->isSubmissionOpen() && (!$p->schoolReturns->first() || in_array($p->schoolReturns->first()->status,['draft','returned_for_correction','not_started'])));
?>
@if($nextWindow && ($nextWindow->extension_date ?? $nextWindow->closing_date))
@include('representative.window-popup',['period'=>$nextWindow])
@endif
<section id="school-next-action" class="overview-next-action"><div class="overview-next-copy"><span class="overview-next-label">NEXT ACTION</span><h2><span class="mobile-only-layout">{{ $nextWindow?($nextWindow->schoolReturns->first()?->status === 'draft' ? 'Your annual return is in progress' : 'Your annual collection is open'):'No open forms awaiting submission' }}</span><span class="desktop-only-layout">{{ $nextWindow ? $nextWindow->title.' is open' : 'No open forms awaiting submission' }}</span></h2></div>@if($nextWindow)<div class="overview-next-deadline"><x-ui.nav-icon name="calendar"/><span>Due {{ ($nextWindow->extension_date??$nextWindow->closing_date)?->format('d F Y') ?? 'Not scheduled' }}</span></div><div class="overview-next-button">@include('representative.collection-action',['period'=>$nextWindow])</div>@else<div class="overview-next-deadline">View your collection statuses below.</div>@endif
<svg class="overview-school-art" viewBox="0 0 300 100" fill="none" stroke="currentColor" aria-hidden="true"><path d="M10 91h280M55 91V42h190v49M45 42l105-32 105 32H45ZM125 91V55h50v36M117 52l33-14 33 14h-66ZM145 91V68h11v23M31 91V61M265 91V53M150 10V1h24v10h-24"/><path d="M65 54h18v14H65zM93 54h18v14H93zM189 54h18v14h-18zM217 54h18v14h-18zM65 77h18v14H65zM93 77h18v14H93zM189 77h18v14h-18zM217 77h18v14h-18z"/><circle cx="30" cy="52" r="15"/><circle cx="266" cy="41" r="20"/></svg></section>

<p class="desktop-only-layout dashboard-statistics-context">
@if($statisticReturn)
Statistics from your saved {{ $statisticReturn->reportingPeriod?->year }} {{ in_array($statisticReturn->status,['draft','returned_for_correction']) ? 'draft' : 'submission' }} &middot; Updated {{ $statisticReturn->updated_at->timezone('Africa/Kampala')->format('j M Y, g:i A') }} EAT
@else
Statistics will appear after you save your school form.
@endif
</p>
<section class="overview-metrics" aria-label="My school statistics">@foreach(array_slice($schoolStatistics,0,4) as $stat)<article class="overview-metric metric-{{ $loop->index }}"><span class="overview-metric-icon"><x-ui.nav-icon :name="$stat['icon']"/></span><div><h2>{{ $stat['label'] }}</h2><strong>@if($stat['value']===null)&mdash;@else{{ number_format($stat['value']) }}@endif</strong><small class="dashboard-metric-description">{{ match($loop->index) { 0 => 'Boys and girls enrolled', 1 => 'Teachers listed in your form', 2 => 'Non-teaching staff listed', default => 'Permanent classrooms recorded' } }}</small></div></article>@endforeach</section>
<nav class="client-shortcuts" aria-label="School services">
<a href="{{ route('representative.profile') }}" class="shortcut-blue"><span><x-ui.nav-icon name="school"/></span><small>SCHOOL</small><strong>School Profile</strong></a>
<a href="{{ route('returns.index') }}" class="shortcut-green"><span><x-ui.nav-icon name="clipboard"/></span><small>REPORTING</small><strong>My Submissions</strong></a>
<a href="{{ route('alerts.index') }}" class="shortcut-red"><span><x-ui.nav-icon name="bell"/></span><small>NOTIFICATIONS</small><strong>School Alerts</strong></a>
<a href="{{ route('support.index') }}" class="shortcut-green"><span><x-ui.nav-icon name="help"/></span><small>SUPPORT</small><strong>Get Help</strong></a>
</nav>

@include('representative.desktop-collections')
@include('representative.desktop-school-statistics')
@endif
</div></x-layouts.app>
