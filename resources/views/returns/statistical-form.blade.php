<x-layouts.app :title="$title" :hide-heading="true">
<div class="source-entry-page paper-entry-page reference-form-page">
    <div class="source-form-heading"><div><h2>{{ $return->school->official_name }}</h2></div>@unless(auth()->user()->isSchoolRepresentative())<x-ui.back-link :href="route('returns.show', $return)" label="Back to submission"/>@endunless</div>
    @if($errors->any())<div class="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="source-entry-layout"><section class="source-entry-sidebar source-entry-top-sections reference-section-navigation" aria-label="Form progress and sections">

@php
 $mobileSectionKeys = array_keys($sections);
 $mobileSectionPosition = array_search($sectionKey, $mobileSectionKeys);
 $mobileSavedCount = count(array_intersect_key($return->statistical_form ?? [], $sections));
 $mobilePercentage = count($sections) ? (int) round($mobileSavedCount / count($sections) * 100) : 0;
@endphp
<div class="mobile-only-layout mobile-section-progress">
 <div class="mobile-progress-heading"><span>Sections saved</span><strong>{{ $mobilePercentage }}%</strong></div>
 <progress value="{{ $mobileSavedCount }}" max="{{ max(1,count($sections)) }}" aria-label="Saved sections">{{ $mobilePercentage }}%</progress>
 <p>{{ $mobileSavedCount }} of {{ count($sections) }} sections saved</p>
 @if($editable)
 @if(empty($qualityErrors))
 <span class="mobile-readiness is-ready">Ready for final review</span>
 @else
 <section class="mobile-readiness-errors" aria-labelledby="mobile-readiness-error-title">
 <header><span class="mobile-error-symbol" aria-hidden="true">!</span><div><h3 id="mobile-readiness-error-title">{{ count($qualityErrors) }} {{ Str::plural('error',count($qualityErrors)) }} to fix</h3><p>Further checks needed before submission</p></div></header>
 <ul>@foreach($qualityErrors as $issueKey=>$issue)
 @php($issueSection = array_key_exists($issueKey,$sections) ? $issueKey : explode('_',$issueKey)[0])
 @php($issueSection = str_starts_with($issueKey,'facility_') ? 'facilities' : $issueSection)
 <li>@if(array_key_exists($issueSection,$sections))<a href="{{ route('returns.form.show',[$return,$issueSection]) }}"><span>{{ $issue }}</span><span class="mobile-error-action">Fix &rarr;</span></a>@else<span>{{ $issue }}</span>@endif</li>
 @endforeach</ul>
 </section>
 @endif
 @endif

</div>
<nav class="source-form-tabs" aria-label="Source form sections">@foreach($sections as $key => $item)
<a class="{{ array_key_exists($key,$return->statistical_form ?? []) ? 'is-section-saved' : '' }}" href="{{ route('returns.form.show', [$return, $key]) }}" @if($key === $sectionKey) aria-current="page" @endif>
@if(array_key_exists($key,$return->statistical_form ?? []))<span class="reference-saved-check" aria-label="Section saved">&#10003;</span>@endif
<span>{{ chr(64+$loop->iteration) }}. {{ preg_replace('/^[0-9\-]+\.\s*/', '', $item['title']) }}</span></a>
@endforeach</nav>
<div class="reference-progress-caption">{{ count(array_intersect_key($return->statistical_form ?? [], $sections)) }} of {{ count($sections) }} sections saved</div>
</section><div class="source-entry-content {{ $sectionKey === 'identity' ? 'profile-capture-inline' : '' }}">
    <section class="card reference-form-card"><header class="card-header reference-form-header"><h2>Section {{ chr(65+array_search($sectionKey,array_keys($sections))) }}: {{ preg_replace('/^[0-9\-]+\.\s*/', '', $section['title']) }}</h2><span>{{ $editable ? 'Your progress saves automatically' : 'Read-only submission' }}</span></header>
        <form data-protect-changes @if($editable) data-autosave-url="{{ route('returns.form.autosave', [$return, $sectionKey]) }}" data-autosave-key="{{ auth()->id() }}-{{ $return->id }}-{{ $sectionKey }}" data-draft-saved-at="{{ $draftSavedAt }}" @endif method="POST" action="{{ route('returns.form.save', [$return, $sectionKey]) }}" class="resource-form source-form">@csrf
            <input type="hidden" name="draft_revision" value="{{ $draftRevision }}">
            @if($editable)<div class="form-autosave-status" data-autosave-status role="status" aria-live="polite">Changes save automatically as you type.</div>@endif
            <input type="hidden" name="data[_section]" value="{{ $sectionKey }}">
            <div class="form-grid">@foreach($section['fields'] ?? [] as $key => $field)<div class="source-field source-field-{{ $key }}"><label for="control-{{ md5('data['.$key.']') }}">{{ $field['label'] }}</label><x-ui.source-field :field="$field" :name="'data['.$key.']'" :value="old('data.'.$key, $data[$key] ?? null)" :disabled="!$editable || in_array($key,$confirmedKeys ?? [])" /></div>@endforeach</div>
            @foreach($section['tables'] ?? [] as $tableKey => $table)
                <?php
                    $rows = old('data.'.$tableKey, $data[$tableKey] ?? []);
                    $rowLabels = $table['rows'] ?? array_fill_keys(array_keys($rows ?: [0 => []]), '');
                    $numericColumns = collect($table['columns'])->filter(fn($f) => in_array($f['type'], ['integer']))->keys()->all();
                ?>
                @if($sectionKey === 'enrolment')
                @include('returns.partials.paper-enrolment')
                @else
                <section data-source-table="{{ $tableKey }}" @if(!isset($table['rows'])) data-repeatable-table @endif><h3>{{ $table['label'] }}</h3><div class="table-wrap"><table class="data-table source-data-table"><thead><tr><th scope="col">{{ isset($table['rows']) ? 'Item' : '#' }}</th>@foreach($table['columns'] as $column)<th scope="col">{{ $column['label'] }}</th>@endforeach @if($numericColumns)<th scope="col">Total</th>@endif @if(!isset($table['rows']) && $editable)<th scope="col">Actions</th>@endif</tr></thead><tbody data-source-rows>
                    @foreach($rowLabels as $rowKey => $label)
                        <tr><th scope="row">{{ $label ?: $loop->iteration }}</th>@foreach($table['columns'] as $columnKey => $column)<td><x-ui.source-field :field="$column" :name="'data['.$tableKey.']['.$rowKey.']['.$columnKey.']'" :value="$rows[$rowKey][$columnKey] ?? null" :disabled="!$editable" /></td>@endforeach
                        @if($numericColumns)<td class="source-total">{{ collect($numericColumns)->sum(fn($column) => (int)($rows[$rowKey][$column] ?? 0)) }}</td>@endif
                        @if(!isset($table['rows']) && $editable)<td><button type="button" class="button" data-remove-source-row>Remove</button></td>@endif</tr>
                    @endforeach
                    </tbody>@if($numericColumns)<tfoot><tr><th>Total</th>@foreach($table['columns'] as $columnKey => $column)<td>{{ in_array($columnKey, $numericColumns) ? collect($rows)->sum($columnKey) : '' }}</td>@endforeach<td>{{ collect($rows)->sum(fn($row) => collect($numericColumns)->sum(fn($column) => (int)($row[$column] ?? 0))) }}</td>@if(!isset($table['rows']) && $editable)<td></td>@endif</tr></tfoot>@endif
                </table></div>
                @if(!isset($table['rows']) && $editable)<button type="button" class="button button-secondary" data-add-source-row>Add row</button>@endif
                @if($numericColumns)<p class="muted">Totals reflect the saved values. Save this section to recalculate.</p>@endif
                </section>
                @endif
            @endforeach
            @if($editable)<div class="form-actions source-save-bar"><button class="button button-primary desktop-only-layout" type="submit">Save this section</button><div class="mobile-only-layout mobile-form-bottom-actions"><p class="mobile-save-indicator" data-autosave-status role="status" aria-live="polite">Changes save automatically</p><p class="mobile-navigation-help">Prev and Next save this section before moving.</p><div class="mobile-three-actions">@if($mobileSectionPosition > 0)<button class="button" type="submit" name="navigate" value="previous">Prev</button>@endif
@if($mobileSectionPosition < count($sections)-1)<button class="button" type="submit" name="navigate" value="next">Next</button>@endif
<button class="button button-primary" type="submit">Save draft</button></div></div></div>@endif
@if(!$editable)<div class="mobile-only-layout mobile-bottom-navigation">@if($mobileSectionPosition > 0)<a class="button" href="{{ route('returns.form.show',[$return,$mobileSectionKeys[$mobileSectionPosition-1]]) }}">&larr; Previous</a>@endif @if($mobileSectionPosition < count($sections)-1)<a class="button" href="{{ route('returns.form.show',[$return,$mobileSectionKeys[$mobileSectionPosition+1]]) }}">Next &rarr;</a>@endif</div>@endif
        </form>
    </section>
    @if(auth()->user()->isSchoolRepresentative())
        @if($return->correction_reason)<div class="alert"><strong>Corrections requested</strong><p>{{ $return->correction_reason }}</p></div>@endif
        @if($editable && $sectionKey === 'declaration')<form method="POST" action="{{ route('returns.transition',$return) }}" class="form-actions {{ $mobileSavedCount < count($sections) || !empty($qualityErrors) ? 'mobile-completion-pending' : '' }}" data-confirm-submission>@csrf<input type="hidden" name="action" value="submit"><input type="hidden" name="acknowledged" value="0"><button class="button button-primary" type="submit"><span class="desktop-only-layout">Submit and lock</span><span class="mobile-only-layout">Complete &amp; submit</span></button></form>@endif
        @if($editable && $sectionKey === 'declaration' && ($mobileSavedCount < count($sections) || !empty($qualityErrors)))<p class="mobile-only-layout mobile-completion-note">Save all {{ count($sections) }} sections and resolve the checks listed above to enable final submission. Saving a draft does not submit your form.</p>@endif
    @endif
</div></div></div>
<dialog id="submission-confirmation" class="submission-confirm-dialog"><form method="dialog"><h2>Submit annual school form?</h2><p>Check your information before submitting. This is your school's submission for {{ $return->reportingPeriod->year }}. Submitted data becomes read-only.</p><label class="checkbox-row"><input type="checkbox" required> I acknowledge that the information is correct and complete, and understand that I cannot edit it after submission.</label><div class="form-actions"><button class="button" value="cancel" formnovalidate>Cancel</button><button class="button button-primary" value="confirm">Confirm submission</button></div></form></dialog>
</x-layouts.app>
