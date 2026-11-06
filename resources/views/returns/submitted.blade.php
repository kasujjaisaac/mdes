<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions"><a class="button" href="{{ route('returns.index') }}">Submissions</a></div></x-slot:topbarFilters>
<div class="submitted-record-page {{ ($adminReview ?? false) ? 'admin-submitted-review' : '' }}">
<a class="submitted-mobile-back" href="{{ route('returns.index') }}">&larr; Back to submissions</a>
@if($errors->any())<div class="announcement-error" role="alert"><strong>Review could not be completed.</strong><p>{{ $errors->first() }}</p></div>@endif
<header class="submitted-record-summary"><div class="submitted-record-year"><span>REPORTING YEAR</span><strong>{{ $return->reportingPeriod->year }}</strong></div><div class="submitted-record-school"><h1>{{ $return->school->official_name }}</h1><p>{{ $return->reportingPeriod->title }} &mdash; annual submission</p></div><div class="submitted-record-date"><span class="submission-status review-status is-{{ $return->status }}"><x-ui.nav-icon name="checklist"/> {{ $return->statusLabel() }}</span>
@if($return->submitted_at)
<div class="submitted-date-value"><span>Date submitted</span><time datetime="{{ $return->submitted_at->toIso8601String() }}">{{ $return->submitted_at->timezone('Africa/Kampala')->format('d F Y, H:i') }}</time></div>
@else
<span class="muted">Submission date not recorded</span>
@endif
</div></header>
@php($sectionTitle = fn($title) => preg_replace('/^[\d\s\p{Pd}]+[.]\s*/u', '', $title))
<nav class="submitted-record-nav" aria-label="Submitted data sections">
@foreach(\App\Support\StatisticalForm::sections() as $key=>$section)
<a href="#submitted-{{ $key }}" @if($loop->first) aria-current="location" @endif>{{ $sectionTitle($section['title']) }}</a>
@endforeach
</nav>
<?php $display = fn($value) => blank($value) ? 'Not recorded' : (is_array($value) ? implode(', ',array_map(fn($v)=>is_scalar($v)?(string)$v:'', $value)) : (string)$value); ?>
@foreach(\App\Support\StatisticalForm::sections() as $key=>$section)
<section id="submitted-{{ $key }}" class="card submitted-data-section"><header class="submitted-section-heading"><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h2>{{ $sectionTitle($section['title']) }}</h2></header><div class="submitted-section-body">
@if(!array_key_exists($key,$return->statistical_form??[]))<p class="submitted-missing-value">Not recorded</p>
@else
<?php $data=$return->statistical_form[$key]; ?>
@if(!empty($section['fields']))<table class="profile-values-table submitted-field-table"><thead><tr><th>Field</th><th>Submitted value</th></tr></thead><tbody>
@foreach($section['fields'] as $fieldKey=>$field)
@php($fieldValue = $key === 'identity' && $fieldKey === 'date' ? ($return->submitted_at?->timezone('Africa/Kampala')->format('d M Y, H:i') ?? 'Submission date not recorded') : $display($data[$fieldKey]??null))
<tr><th scope="row">{{ $key === 'identity' && $fieldKey === 'date' ? 'Date submitted' : ($key === 'identity' && $fieldKey === 'registration_number' ? 'MOE Reg No' : $field['label']) }}</th><td class="{{ in_array($fieldValue,['Not recorded','Submission date not recorded']) ? 'is-missing' : '' }}">{{ $fieldValue }}</td></tr>
@endforeach</tbody></table>
@endif
@foreach($section['tables']??[] as $tableKey=>$table)<h3>{{ $table['label'] }}</h3><div class="table-wrap"><table class="profile-values-table submitted-grid-table"><thead><tr><th>Item</th>
@foreach($table['columns'] as $column)<th>{{ $column['label'] }}</th>
@endforeach</tr></thead><tbody>
@forelse(($table['rows']??array_fill_keys(array_keys($data[$tableKey]??[]),'')) as $rowKey=>$label)<tr><th scope="row">{{ $label ?: $loop->iteration }}</th>
@foreach($table['columns'] as $columnKey=>$column)<td class="{{ blank($data[$tableKey][$rowKey][$columnKey]??null) ? 'is-missing' : '' }}">{{ $display($data[$tableKey][$rowKey][$columnKey]??null) }}</td>
@endforeach</tr>
@empty<tr><td colspan="{{ count($table['columns'])+1 }}">No entries recorded</td></tr>
@endforelse</tbody></table></div>
@endforeach
@endif
</div></section>
@endforeach
@if($adminReview ?? false)
<section class="submitted-review-actions" aria-labelledby="submission-review-title">
<header><div><span class="diocesan-eyebrow">School review</span><h2 id="submission-review-title">Review this submission</h2><p>The school answers above are read-only.</p></div><span class="review-status is-{{ $return->status }}">{{ $return->statusLabel() }}</span></header>
@if(in_array($return->status,\App\Models\SchoolReturn::PENDING_REVIEW_STATUSES) && !$return->reportingPeriod->is_archived)
@can('review',$return)
<div class="submitted-review-buttons"><a class="button" href="{{ route('returns.index') }}">Back to submissions</a><button type="button" class="button review-return-trigger" data-open-correction-dialog>Return for correction</button><form method="POST" action="{{ route('returns.transition',$return) }}">@csrf<input type="hidden" name="action" value="approve"><button class="button button-primary" type="submit">Approve submission</button></form></div>
<dialog class="review-return-dialog submitted-correction-dialog" data-correction-dialog data-reopen="{{ $errors->has('reason') ? 'true' : 'false' }}" aria-labelledby="correction-dialog-title" aria-describedby="correction-dialog-description">
<header><div><span class="diocesan-eyebrow">School submission</span><h2 id="correction-dialog-title">Return for correction</h2></div><button type="button" data-close-correction-dialog aria-label="Close">&times;</button></header>
<p class="correction-school-name">{{ $return->school->official_name }}</p><p id="correction-dialog-description">Describe the issue for the submitter. Sending will return the form so the school can correct and resubmit it.</p>
<form method="POST" action="{{ route('returns.transition',$return) }}">@csrf<input type="hidden" name="action" value="return_for_correction"><label for="review-return-reason">Issue / corrections needed <span aria-hidden="true">*</span></label><textarea id="review-return-reason" name="reason" rows="5" maxlength="3000" required placeholder="Explain the issue and what needs to change">{{ old('reason') }}</textarea>@error('reason')<small class="correction-field-error" role="alert">{{ $message }}</small>@enderror<small>The submitter will receive this message as an alert linked to the form.</small><footer><button type="button" class="button" data-close-correction-dialog>Cancel</button><button class="button button-primary" type="submit">Send</button></footer></form>
</dialog>
@endcan
@elseif($return->status === 'approved')<p class="submitted-review-outcome">Approved {{ $return->approved_at?->timezone('Africa/Kampala')->format('d M Y, H:i') }}. This form remains locked.</p>
@elseif($return->status === 'returned_for_correction')<p class="submitted-review-outcome">Awaiting the school's corrections and resubmission.</p>
@else<p class="submitted-review-outcome">This reporting window is archived. The form is available for viewing.</p>
@endif
@if($return->correction_reason)<div class="submitted-review-reason"><strong>Correction requested</strong><p>{{ $return->correction_reason }}</p></div>@endif
</section>
<section class="submitted-review-history"><h2>Review history</h2>@forelse($reviewHistory as $event)<article><strong>{{ match($event->action) { 'submission.submit'=>'Submitted for review', 'submission.approve'=>'Approved', default=>'Returned for correction' } }}</strong><small>{{ $event->user?->name ?? 'Previous user' }} &middot; {{ $event->created_at->timezone('Africa/Kampala')->format('d M Y, H:i') }}</small>@if($event->reason)<p>{{ $event->reason }}</p>@endif</article>@empty<p>No review actions recorded yet.</p>@endforelse</section>
@endif

</div>
</x-layouts.app>
