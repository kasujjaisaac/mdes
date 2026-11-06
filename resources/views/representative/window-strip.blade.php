@if(auth()->user()->isSchoolRepresentative() && auth()->user()->school_id)
<?php
$stripSchoolId=auth()->user()->school_id;
$stripWindow=\App\Models\ReportingPeriod::where('status','open')->where('is_archived',false)->where(fn($q)=>$q->whereNull('opening_date')->orWhereDate('opening_date','<=',now('Africa/Kampala')->toDateString()))->whereRaw('(COALESCE(extension_date, closing_date) IS NULL OR COALESCE(extension_date, closing_date) >= ?)', [now('Africa/Kampala')->toDateString()])->whereHas('schools',fn($q)=>$q->where('schools.id',$stripSchoolId))->with(['schoolReturns'=>fn($q)=>$q->select('id','school_id','reporting_period_id','status')->where('school_id',$stripSchoolId)])->orderByDesc('year')->orderByDesc('id')->first();
$stripEntry=$stripWindow?->schoolReturns->first();
$stripSubmitted=$stripEntry && in_array($stripEntry->status,['submitted','certified','approved','locked']);
?>
@if($stripWindow)
<?php
$stripDeadline=$stripWindow->extension_date ?? $stripWindow->closing_date;
$stripMessage=$stripSubmitted ? 'Your '.$stripWindow->year.' school submission has been received.' : $stripWindow->year.' data submission is open'.($stripDeadline ? ' - closes '.$stripDeadline->format('j F Y') : '');
?>


<aside class="submission-window-strip scrolling-window-announcement" aria-label="Submission window announcement"><x-ui.nav-icon name="bell"/><div class="window-strip-track"><div class="window-strip-loop">
@for($copy=0;$copy<2;$copy++)
<div class="window-strip-copy" @if($copy===1) aria-hidden="true" @endif><span><svg class="marquee-message-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M4 4h16v12H9l-5 4V4Z"/><path d="M8 8h8M8 12h6"/></svg>{{ $stripMessage }}</span><span aria-hidden="true"><svg class="marquee-message-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 4h16v12H9l-5 4V4Z"/><path d="M8 8h8M8 12h6"/></svg>{{ $stripMessage }}</span></div>
@endfor
</div></div><button type="button" class="mobile-announcement-toggle" data-announcement-toggle aria-expanded="false">Read notice</button><a href="{{ $stripSubmitted?route('returns.show',$stripEntry):route('representative.home') }}">{{ $stripSubmitted?'View submission':'School forms' }} &rarr;</a></aside><div class="mobile-announcement-full" hidden>{{ $stripMessage }}</div>
@endif
@endif
