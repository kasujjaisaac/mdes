<x-layouts.app :title="$title" :hide-heading="true">

<div class="alerts-workspace alerts-refined communications-inbox announcement-workspace announcement-directory">
@include('announcements.navigation', ['communicationsTitle' => 'Inbox', 'communicationsDescription' => 'Keep up with school messages, announcements and support replies.', 'communicationsTab' => 'inbox'])
<section class="alerts-feed alerts-inbox-panel">@if($messageCount > 0)<header class="alerts-panel-title"><div><x-ui.nav-icon name="bell"/><h1>Messages</h1><span class="alerts-message-total">{{ $alerts->total() }}</span></div><span class="desktop-only-layout desktop-inbox-state"><i class="{{ $unreadCount ? 'has-unread' : '' }}" aria-hidden="true"></i>{{ $unreadCount ? $unreadCount.' unread '.Str::plural('message',$unreadCount) : 'No unread messages' }}</span><span class="alerts-security-label"><x-ui.nav-icon name="shield"/> School communications</span></header>
<header class="alerts-inbox-header"><nav aria-label="Message filters"><a href="{{ route('alerts.index') }}" class="{{ request('filter')!=='unread'?'is-active':'' }}" @if(request('filter')!=='unread') aria-current="page" @endif>All messages <span class="desktop-only-layout desktop-alert-filter-count">{{ $messageCount }}</span></a><a href="{{ route('alerts.index',['filter'=>'unread']) }}" class="{{ request('filter')==='unread'?'is-active':'' }}" @if(request('filter')==='unread') aria-current="page" @endif>Unread <span class="desktop-only-layout desktop-alert-filter-count">{{ $unreadCount }}</span></a></nav></header>
@endif
<div class="alerts-inbox-messages">
@forelse($alerts as $alert)
<article class="school-alert {{ !$alert->read_at && $alert->user_id===auth()->id()?'is-unread':'' }}"><div class="school-alert-icon"><x-ui.nav-icon :name="in_array($alert->type,['support_request','support_reply']) ? 'help' : 'document'"/></div><div class="school-alert-content"><div class="alert-message-category"><span>{{ in_array($alert->type,['support_request','support_reply']) ? 'Support' : (in_array($alert->type,['announcement','submission_window']) ? 'Announcement' : ($alert->type==='school_message' ? 'School message' : 'School feedback')) }}</span>@if(!$alert->read_at && $alert->user_id===auth()->id())<span class="alert-new-label">New</span>@endif</div><header><h2>{{ $alert->subject ?? ucfirst(str_replace('_',' ',$alert->type)) }}</h2><time>{{ $alert->created_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</time></header><small>{{ $alert->sender?->name ?? config('mdsiss.name') }}
@if($alert->school)
 &middot; {{ $alert->school->official_name }}
@endif
@if($alert->schoolReturn)
 &middot; {{ $alert->schoolReturn->reportingPeriod?->year }}
@endif
</small><p>{{ $alert->message }}</p><footer>
@if($alert->type==='submission_window')<a href="{{ route('representative.home') }}">Open school forms</a>@endif
@if(in_array($alert->type,['support_request','support_reply']))
<a href="{{ route('support.index') }}">Open support &rarr;</a>
@endif
@if($alert->school_return_id)
<a href="{{ route('returns.show',$alert->school_return_id) }}">View submission &rarr;</a>
@endif
@if($alert->user_id===auth()->id() && !$alert->read_at)
<form method="POST" action="{{ route('alerts.read',$alert) }}">@csrf<button class="button">{{ $alert->type === 'announcement' ? 'I have read this announcement' : 'Mark as read' }}</button></form>
@else
<span>{{ $alert->read_at?'Read':'Sent' }}</span>
@endif
</footer></div></article>
@empty
<div class="announcement-directory-empty"><span class="announcement-empty-icon"><x-ui.nav-icon name="bell"/></span><h2>{{ request('filter')==='unread' ? 'You are all caught up' : 'Your inbox is ready' }}</h2><p>{{ request('filter')==='unread' ? 'You have no unread messages. You can still view your earlier messages.' : 'School messages, announcements and support replies will appear here.' }}</p>
@if(request('filter')==='unread')<a class="button button-primary" href="{{ route('alerts.index') }}">View all messages</a>@else<a class="button button-primary" href="{{ route('dashboard') }}">Back to dashboard</a>@endif
</div>
@endforelse
{{ $alerts->links() }}</div></section></div></x-layouts.app>
