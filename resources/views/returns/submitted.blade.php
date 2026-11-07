<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions"><a class="button" href="{{ route('returns.index') }}">Submissions</a></div></x-slot:topbarFilters>
<?php $reviewMode = $reviewMode ?? false; $submittedSections = $return->relationLoaded('displayVersion') ? ($return->getRelation('displayVersion')?->payload['schema'] ?? \App\Support\StatisticalForm::sectionsForReturn($return)) : \App\Support\StatisticalForm::sectionsForReturn($return); $missingSubmittedCopy = $return->relationLoaded('displayVersion') && !$return->getRelation('displayVersion') && $return->status==='returned_for_correction'; if($missingSubmittedCopy)$submittedSections=[]; ?>
@if(!$missingSubmittedCopy)
@include('returns.partials.download-copy',['copyReturn'=>$return])
@endif
@if($missingSubmittedCopy)<div class="announcement-error" role="status">The original submitted copy was not retained for this older form. The school's working corrections remain private until resubmission.</div>@endif
<div class="submitted-record-page {{ ($adminReview ?? false) ? 'admin-submitted-review' : '' }}">
@unless(auth()->user()->isSchoolRepresentative())<a class="submitted-mobile-back" href="{{ route('returns.index') }}">&larr; Back to submissions</a>@endunless
@if($errors->any())<div class="announcement-error" role="alert"><strong>Review could not be completed.</strong><p>{{ $errors->first() }}</p></div>@endif
@if($reviewMode)
<section class="form-review-intro"><span class="diocesan-eyebrow">Final step</span><h1>Review and submit</h1><p>Review your school's answers below. Use Edit section to make changes.</p>
@if($qualityErrors)<div class="review-completeness has-issues" role="alert"><h2>{{ count($qualityErrors) }} items needing attention</h2><ul>
@foreach($qualityErrors as $issueKey=>$issue)
@php
$target = array_key_exists($issueKey,\App\Support\StatisticalForm::sections()) ? $issueKey : explode('_',$issueKey)[0];
$target = $target === 'religion' ? 'enrolment' : ($target === 'facility' ? 'facilities' : $target);
$target = array_key_exists($target,\App\Support\StatisticalForm::sectionsForReturn($return)) ? $target : 'identity';
@endphp
<li><a href="{{ route('returns.form.show',[$return,$target]) }}">{{ $issue }}</a></li>
@endforeach</ul></div>@else<div class="review-completeness is-ready"><strong>Ready to submit</strong><p>All required sections are complete.</p></div>@endif
</section>
@endif
<header class="submitted-record-summary"><div class="submitted-record-year"><span>REPORTING YEAR</span><strong>{{ $return->reportingPeriod->year }}</strong></div><div class="submitted-record-school"><h1>{{ $return->school->official_name }}</h1><p>{{ $return->reportingPeriod->title }} &mdash; annual submission</p></div><div class="submitted-record-date"><span class="submission-status review-status is-{{ $return->status }}"><x-ui.nav-icon name="checklist"/> {{ $return->statusLabel() }}</span>
@if($return->submitted_at)
<div class="submitted-date-value"><span>Date submitted</span><time datetime="{{ $return->submitted_at->toIso8601String() }}">{{ $return->submitted_at->timezone('Africa/Kampala')->format('d F Y, H:i') }}</time></div>
@else
<span class="muted">{{ $reviewMode ? 'Not yet submitted' : 'Submission date not recorded' }}</span>
@endif
</div></header>
@php($sectionTitle = fn($title) => $title)
<nav class="submitted-record-nav" aria-label="Submitted data sections">
@foreach(collect($submittedSections)->except('declaration') as $key=>$section)
<a href="#submitted-{{ $key }}" @if($loop->first) aria-current="location" @endif>{{ $sectionTitle($section['title']) }}</a>
@endforeach
</nav>
<?php $display = fn($value) => blank($value) ? 'Not recorded' : (is_array($value) ? implode(', ',array_map(fn($v)=>is_scalar($v)?(string)$v:'', $value)) : (string)$value); ?>
@foreach(collect($submittedSections)->except('declaration') as $key=>$section)
<section id="submitted-{{ $key }}" class="card submitted-data-section"><header class="submitted-section-heading"><span>{{ $section['page'] }}</span><h2>{{ $key === 'identity' ? 'School profile' : $sectionTitle($section['title']) }}</h2>@if($reviewMode)<a class="button review-edit-section" href="{{ route('returns.form.show',[$return,$key]) }}">Edit section</a>@endif</header><div class="submitted-section-body">@if(!empty($section['description']))<p class="questionnaire-source-description">{{ $section['description'] }}</p>@endif
@if(!array_key_exists($key,$return->statistical_form??[]))<p class="submitted-missing-value">Not recorded</p>
@else
<?php $data=$return->statistical_form[$key]; if($key === 'enrolment') $data['community'] = $return->statistical_form['religion']['community'] ?? []; ?>
@if(!empty($section['fields']))<table class="profile-values-table submitted-field-table"><thead><tr><th>Field</th><th>Submitted value</th></tr></thead><tbody>
@foreach($section['fields'] as $fieldKey=>$field)
@continue($key === 'utilities' && $fieldKey === 'water_explanation' && ($data['water_reliable'] ?? null) !== 'No')
@if($key === 'identity' && $fieldKey === 'level')<tr><th colspan="2">{{ $section['classification_title'] }}</th></tr>@endif
@php($fieldValue = $key === 'identity' && $fieldKey === 'date' ? ($reviewMode ? $display($data[$fieldKey] ?? null) : ($return->submitted_at?->timezone('Africa/Kampala')->format('d M Y, H:i') ?? 'Submission date not recorded')) : $display($data[$fieldKey]??null))
<tr><th scope="row">{{ $field['label'] }}</th><td class="{{ in_array($fieldValue,['Not recorded','Submission date not recorded']) ? 'is-missing' : '' }}">{{ $fieldValue }}</td></tr>
@endforeach</tbody></table>
@endif
@foreach($section['tables']??[] as $tableKey=>$table)
@if($tableKey === 'community')
@include('returns.partials.religious-affiliation', ['rows' => $data[$tableKey] ?? [], 'editable' => false])
@else
<h3>{{ $table['label'] }}</h3>@if(!empty($table['caption']))<h4 class="questionnaire-table-caption">{{ $table['caption'] }}</h4>@endif<div class="table-wrap"><table class="profile-values-table submitted-grid-table"><thead><tr><th>{{ $table['row_heading'] ?? 'No.' }}</th>
@foreach($table['columns'] as $column)<th>{{ $column['display_label'] ?? $column['label'] }}</th>
@endforeach</tr></thead><tbody>
@forelse(($table['rows']??array_fill_keys(array_keys($data[$tableKey]??[]),'')) as $rowKey=>$label)<tr><th scope="row">{{ $label ?: $loop->iteration }}</th>
@foreach($table['columns'] as $columnKey=>$column)<td class="{{ blank($data[$tableKey][$rowKey][$columnKey]??null) ? 'is-missing' : '' }}">{{ $display($data[$tableKey][$rowKey][$columnKey]??null) }}@if($columnKey === 'remarks' && isset($table['row_notes'][$rowKey]))<small class="questionnaire-source-note">{{ $table['row_notes'][$rowKey] }}</small>@endif</td>
@endforeach</tr>
@empty<tr><td colspan="{{ count($table['columns'])+1 }}">No entries recorded</td></tr>
@endforelse</tbody></table></div>
@endif
@endforeach
@endif
@if(!empty($section['note']))<p class="questionnaire-source-note">{{ $section['note'] }}</p>@endif
</div></section>
@endforeach
@if($reviewMode)
<section class="review-submit-panel" aria-labelledby="review-submit-title"><h2 id="review-submit-title">Confirm your submission</h2><p>After submission, your form is locked for admin review. You can edit it again if it is returned for correction.</p>
<form method="POST" action="{{ route('returns.transition',$return) }}" data-review-submit>@csrf<input type="hidden" name="action" value="submit"><input type="hidden" name="review_fingerprint" value="{{ $reviewFingerprint }}">
<label class="review-acknowledgement"><input type="checkbox" name="acknowledged" value="1" required @checked(old('acknowledged'))><span>I acknowledge that I have reviewed this form and that the information provided is correct to the best of my knowledge.</span></label>
@error('acknowledged')<p class="correction-field-error">{{ $message }}</p>@enderror
<div class="form-actions"><a class="button" href="{{ route('returns.form.show',[$return,'studies']) }}">Back to edit</a><button type="submit" class="button button-primary" @disabled(count($qualityErrors)>0)>Submit form</button></div>
@if($qualityErrors)<p class="muted">Correct the items listed above before submitting.</p>@endif
<p data-review-submit-status role="status" hidden>Submitting your form...</p></form></section>
@elseif($return->submitted_at)
<section class="review-submission-receipt"><strong>Submission reference: MDES-{{ $return->id }}</strong><p>Submitted {{ $return->submitted_at->timezone('Africa/Kampala')->format('d F Y, H:i') }}. This submitted form is read-only.</p></section>
@endif
@if(($adminReview ?? false) && $return->status==='returned_for_correction' && !$missingSubmittedCopy)<p class="review-submission-receipt">This is the last submitted copy. The school's working corrections remain private until resubmission.</p>@endif
@if($adminReview ?? false)
<section class="submitted-review-actions" aria-labelledby="submission-review-title">
<header><div><span class="diocesan-eyebrow">School review</span><h2 id="submission-review-title">Review this submission</h2><p>The school answers above are read-only.</p></div><span class="review-status is-{{ $return->status }}">{{ $return->statusLabel() }}</span></header>
@if(in_array($return->status,\App\Models\SchoolReturn::PENDING_REVIEW_STATUSES) && !$return->reportingPeriod->is_archived)
@can('review',$return)
<div class="submitted-review-buttons"><a class="button" href="{{ route('returns.index') }}">Back to submissions</a><button type="button" class="button review-return-trigger" data-open-correction-dialog>Return for correction</button><form method="POST" action="{{ route('returns.transition',$return) }}">@csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}"><input type="hidden" name="action" value="approve"><button class="button button-primary" type="submit">Approve submission</button></form></div>
<dialog class="review-return-dialog submitted-correction-dialog" data-correction-dialog data-reopen="{{ $errors->has('reason') ? 'true' : 'false' }}" aria-labelledby="correction-dialog-title" aria-describedby="correction-dialog-description">
<header><div><span class="diocesan-eyebrow">School submission</span><h2 id="correction-dialog-title">Return for correction</h2></div><button type="button" data-close-correction-dialog aria-label="Close">&times;</button></header>
<p class="correction-school-name">{{ $return->school->official_name }}</p><p id="correction-dialog-description">Describe the issue for the submitter. Sending will return the form so the school can correct and resubmit it.</p>
<form method="POST" action="{{ route('returns.transition',$return) }}">@csrf<input type="hidden" name="review_token" value="{{ $reviewToken }}"><input type="hidden" name="action" value="return_for_correction"><label for="review-return-reason">Issue / corrections needed <span aria-hidden="true">*</span></label><textarea id="review-return-reason" name="reason" rows="5" maxlength="3000" required placeholder="Explain the issue and what needs to change">{{ old('reason') }}</textarea>@error('reason')<small class="correction-field-error" role="alert">{{ $message }}</small>@enderror<small>The submitter will receive this message as an alert linked to the form.</small><footer><button type="button" class="button" data-close-correction-dialog>Cancel</button><button class="button button-primary" type="submit">Send</button></footer></form>
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
