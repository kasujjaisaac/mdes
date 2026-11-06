<x-layouts.app :title="$title" :hide-heading="true">

<div class="alerts-workspace alerts-refined">
<div class="desktop-only-layout desktop-alerts-intro"><div><span>COMMUNICATIONS</span><h2>Stay up to date with your school</h2><p>School feedback, collection updates and support replies, together in one inbox.</p></div><a href="{{ route('support.index') }}"><x-ui.nav-icon name="help"/>Need assistance?</a></div>

@if($canSend)
<details class="card alert-compose" @if(request('return_id') || $errors->any()) open @endif><summary><span><x-ui.nav-icon name="document"/> Send school feedback</span><span class="compose-expand">+</span></summary><form method="POST" action="{{ route('alerts.store') }}" class="resource-form">@csrf
<label>School submission<select name="school_return_id" required><option value="">Select school and reporting year</option>
@foreach($submissions as $submission)
<option value="{{ $submission->id }}" @selected(old('school_return_id',request('return_id'))==$submission->id)>{{ $submission->school->official_name }} &middot; {{ $submission->reportingPeriod->year }}</option>
@endforeach
</select></label><label>Subject<input name="subject" value="{{ old('subject') }}" required maxlength="180" placeholder="Feedback subject"></label><label>Message<textarea name="message" rows="4" required maxlength="5000" placeholder="Write your feedback to the school">{{ old('message') }}</textarea></label><button class="button button-primary">Send feedback</button></form></details>
@endif
<section class="alerts-feed alerts-inbox-panel"><header class="alerts-panel-title"><div><x-ui.nav-icon name="bell"/><h1>{{ $canSend?'School communications':'Your inbox' }}</h1><span class="alerts-message-total">{{ $alerts->total() }}</span></div><span class="desktop-only-layout desktop-inbox-state"><i class="{{ $unreadCount ? 'has-unread' : '' }}" aria-hidden="true"></i>{{ $unreadCount ? $unreadCount.' unread '.Str::plural('message',$unreadCount) : 'No unread messages' }}</span><span class="alerts-security-label"><x-ui.nav-icon name="shield"/> School communications</span></header>
<header class="alerts-inbox-header"><nav aria-label="Alert filters"><a href="{{ route('alerts.index') }}" class="{{ request('filter')!=='unread'?'is-active':'' }}" @if(request('filter')!=='unread') aria-current="page" @endif>All messages <span class="desktop-only-layout desktop-alert-filter-count">{{ $messageCount }}</span></a><a href="{{ route('alerts.index',['filter'=>'unread']) }}" class="{{ request('filter')==='unread'?'is-active':'' }}" @if(request('filter')==='unread') aria-current="page" @endif>Unread <span class="desktop-only-layout desktop-alert-filter-count">{{ $unreadCount }}</span></a></nav></header>
<div class="alerts-inbox-messages">
@forelse($alerts as $alert)
<article class="school-alert {{ !$alert->read_at && $alert->user_id===auth()->id()?'is-unread':'' }}"><div class="school-alert-icon"><x-ui.nav-icon :name="in_array($alert->type,['support_request','support_reply']) ? 'help' : 'document'"/></div><div class="school-alert-content"><div class="alert-message-category"><span>{{ in_array($alert->type,['support_request','support_reply']) ? 'Support' : 'School feedback' }}</span>@if(!$alert->read_at && $alert->user_id===auth()->id())<span class="alert-new-label">New</span>@endif</div><header><h2>{{ $alert->subject ?? ucfirst(str_replace('_',' ',$alert->type)) }}</h2><time>{{ $alert->created_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</time></header><small>{{ $alert->sender?->name ?? config('mdsiss.name') }}
@if($alert->school)
 &middot; {{ $alert->school->official_name }}
@endif
@if($alert->schoolReturn)
 &middot; {{ $alert->schoolReturn->reportingPeriod?->year }}
@endif
</small><p>{{ $alert->message }}</p><footer>
@if(in_array($alert->type,['support_request','support_reply']))
<a href="{{ route('support.index') }}">Open support &rarr;</a>
@endif
@if($alert->school_return_id)
<a href="{{ route('returns.show',$alert->school_return_id) }}">View submission &rarr;</a>
@endif
@if($alert->user_id===auth()->id() && !$alert->read_at)
<form method="POST" action="{{ route('alerts.read',$alert) }}">@csrf<button class="button">Mark as read</button></form>
@else
<span>{{ $alert->read_at?'Read':'Sent' }}</span>
@endif
</footer></div></article>
@empty
<div class="alerts-empty alerts-empty-centered"><div class="alerts-empty-symbol"><x-ui.nav-icon name="bell"/><span aria-hidden="true">&#10003;</span></div><h2>{{ request('filter')==='unread'?'You are all caught up':'No alerts yet' }}</h2><p>{{ request('filter')==='unread'?'You have no unread messages.':'Administrator feedback and school updates will appear here.' }}</p>
@if(request('filter')==='unread')
<a class="button" href="{{ route('alerts.index') }}">View all messages</a>
@else
<a class="alerts-return-link" href="{{ route('dashboard') }}">Back to dashboard &rarr;</a>
@endif
<div class="desktop-only-layout desktop-alert-empty-help"><span>Need to ask a question?</span><a href="{{ route('support.index') }}">Contact support &rarr;</a></div>
</div>
@endforelse
{{ $alerts->links() }}</div></section></div></x-layouts.app>
