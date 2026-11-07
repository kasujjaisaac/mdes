<?php $deadline=$period->extension_date ?? $period->closing_date; $days=max(0,(int)now('Africa/Kampala')->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($deadline->toDateString(),'Africa/Kampala')->startOfDay(),false)); ?>
<dialog class="window-announcement" data-window-announcement data-popup-key="{{ auth()->id() }}-{{ $period->id }}-{{ $deadline->toDateString() }}" aria-labelledby="window-announcement-title" aria-describedby="window-announcement-description">
<header class="window-announcement-hero">
    <span class="window-announcement-status"><span></span> Submission window open</span>
    <button type="button" data-close-announcement aria-label="Close notification"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    <div class="window-announcement-emblem"><x-ui.nav-icon name="calendar"/></div>
    <p class="window-announcement-eyebrow">ANNUAL SCHOOL COLLECTION &middot; {{ $period->year }}</p>
    <h2 id="window-announcement-title">Your school&#39;s story.<br>Your next submission.</h2>
    <p id="window-announcement-description">Bring your school&#39;s records up to date.<br>Complete and submit your annual statistics on time.</p>
</header>
<div class="window-announcement-body">
    <div class="window-announcement-school"><span class="window-announcement-school-icon"><x-ui.nav-icon name="school"/></span><div><span>PREPARED FOR</span><strong>{{ $school->official_name }}</strong></div></div>
    <div class="window-announcement-countdown"><div><strong data-days-remaining data-deadline="{{ $deadline->toDateString() }}">{{ $days }}</strong><span>days remaining</span></div><div><span>Submission deadline</span><strong>{{ $deadline->format('j F Y') }}</strong><small>11:59 PM &middot; Uganda time (EAT)</small></div></div>
    <p class="window-announcement-note"><x-ui.nav-icon name="shield"/><span>You can save your progress and return before the deadline.</span></p>
    <footer><button class="button window-announcement-later" type="button" data-close-announcement>Maybe later</button>@include('representative.collection-action',['period'=>$period])</footer>
</div>
</dialog>
