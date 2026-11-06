<x-layouts.app :title="$title" :hide-heading="true"><div class="support-workspace">
<section class="card support-request-form"><h1>How can we help?</h1><p>Describe the challenge you are facing. An administrator will respond here and in Alerts.</p>@if($errors->any())<div class="mobile-only-layout mobile-validation-summary" role="alert"><strong>Please check your request.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('support.store') }}" class="resource-form" data-protect-changes>@csrf<label>Area<select name="category" required><option value="">Select an area</option>
@foreach(['School profile','Form entry','Submission','Account access','Other'] as $category)
<option @selected(old('category')===$category)>{{ $category }}</option>
@endforeach
</select></label><label>What happened?<textarea name="description" rows="4" required minlength="10" maxlength="3000" placeholder="Describe the problem and the page where it happened. Do not include your password.">{{ old('description') }}</textarea></label><button class="button button-primary">Send support request</button></form></section>
<section class="support-history"><h2>{{ $manager?'Support requests':'My support requests' }}</h2>
@forelse($tickets as $ticket)
<article class="card support-ticket" id="ticket-{{ $ticket->id }}"><header><strong>#{{ $ticket->id }} ? {{ $ticket->category }}</strong><span class="submission-status">{{ ucfirst($ticket->status) }}</span><time>{{ $ticket->created_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</time></header><p>{{ $ticket->description }}</p>
@foreach($ticket->messages as $message)
<div class="support-reply"><strong>Administrator response</strong><p>{{ $message->message }}</p><small>{{ $message->created_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</small></div>
@endforeach
@if($manager)
<form method="POST" action="{{ route('support.reply',$ticket) }}" class="resource-form">@csrf<label>Reply<textarea name="message" rows="3" required maxlength="3000"></textarea></label><label>Status<select name="status"><option value="open">Open</option><option value="resolved" @selected($ticket->status==='resolved')>Resolved</option></select></label><button class="button button-primary">Send reply</button></form>
@endif
</article>
@empty
<div class="alerts-empty"><h2>No support requests yet</h2><p>Your requests and administrator responses will appear here.</p></div>
@endforelse
{{ $tickets->links() }}</section></div></x-layouts.app>
