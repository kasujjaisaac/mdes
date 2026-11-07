<?php $entry=$period->schoolReturns->first(); $sent=$entry && in_array($entry->status,['submitted','certified','approved','locked']); ?>
@if($sent)<a class="button overview-action" href="{{ route('returns.show',$entry) }}">View submitted form &rarr;</a>
@elseif($entry && in_array($entry->status,['draft','returned_for_correction']) && ($entry->status==='returned_for_correction' ? !$period->is_archived : $period->allowsRepresentativeDraft()))<a class="button button-primary overview-action" href="{{ !$entry->profile_confirmed_at?route('returns.form.introduction',$entry):route('returns.form.show',$entry) }}">{{ $entry->status==='returned_for_correction'?'Continue corrections':'Continue draft' }} &rarr;</a>
@elseif($entry)<a class="button overview-action" href="{{ route('returns.show',$entry) }}">View saved form</a>
@elseif($period->allowsRepresentativeDraft())<form method="POST" action="{{ route('returns.start',[$period,$school]) }}">@csrf<button type="submit" class="button button-primary overview-action">{{ $period->isSubmissionOpen()?'Start form':'Prepare draft' }} &rarr;</button></form>
@else<button class="button overview-action" disabled>Window closed</button>@endif
