@if(auth()->user()->isSchoolRepresentative())
@php($pendingAnnouncement = \App\Models\NotificationRecord::where('user_id',auth()->id())->where('school_id',auth()->user()->school_id)->where('type','announcement')->whereNotNull('announcement_id')->whereNull('read_at')->oldest('id')->first())
@if($pendingAnnouncement)
<dialog class="required-announcement" data-required-announcement aria-labelledby="required-announcement-title" aria-describedby="required-announcement-instruction" aria-modal="true" open>
<header><span>School announcement</span><h2 id="required-announcement-title">{{ $pendingAnnouncement->subject }}</h2><time>{{ $pendingAnnouncement->created_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</time></header>
<div class="required-announcement-message">{{ $pendingAnnouncement->message }}</div>
<footer><p id="required-announcement-instruction">Please read this announcement and confirm on behalf of your school.</p><form method="POST" action="{{ route('alerts.read',$pendingAnnouncement) }}">@csrf<button class="button button-primary" type="submit">I have read this announcement</button></form></footer>
</dialog>
@endif
@endif
