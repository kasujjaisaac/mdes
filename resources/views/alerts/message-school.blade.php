<x-layouts.app :title="$title" :hide-heading="true">
<section class="school-message-page">
<header><a href="{{ route('schools.index') }}">Back to Registered Schools</a><h1>Message school</h1></header>
<div class="school-message-recipient"><span>To</span><strong>{{ $school->official_name }}</strong><small>{{ $school->parish?->name }}</small></div>
<form method="POST" action="{{ route('schools.message.store',$school) }}">@csrf
@if($errors->any())<div class="alert" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<label for="school-message-subject">Subject<input id="school-message-subject" name="subject" value="{{ old('subject') }}" required maxlength="180" autofocus></label>
<label for="school-message-body">Message<textarea id="school-message-body" name="message" rows="6" required maxlength="5000">{{ old('message') }}</textarea></label>
<p>The school will receive this message as an alert.</p><div class="school-message-actions"><a href="{{ route('schools.index') }}">Cancel</a><button class="button button-primary" type="submit">Send message</button></div>
</form></section>
</x-layouts.app>
