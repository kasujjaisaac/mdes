<x-layouts.app :title="$title" :hide-heading="true">
<div class="source-entry-page paper-entry-page reference-form-page statistical-entry-refined">
    @unless(auth()->user()->isSchoolRepresentative())<x-ui.back-link :href="route('returns.show', $return)" label="Back to submission"/>@endunless
    @if($errors->any())<div class="alert"><strong>Please correct the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="source-entry-layout"><section class="source-entry-sidebar source-entry-top-sections reference-section-navigation" aria-label="Form progress and sections">

@php
 $mobileSectionKeys = array_keys($sections);
 $mobileSectionPosition = array_search($sectionKey, $mobileSectionKeys);
 $mobileSavedCount = \App\Support\StatisticalForm::savedPageCount($return);
 $mobileDataPageCount = count($sections) - 1;
 $mobilePercentage = $mobileDataPageCount ? (int) round($mobileSavedCount / $mobileDataPageCount * 100) : 0;
@endphp
<div class="mobile-only-layout mobile-section-progress">
 <div class="mobile-progress-heading"><span>Sections saved</span><strong>{{ $mobilePercentage }}%</strong></div>
 <progress value="{{ $mobileSavedCount }}" max="{{ max(1,$mobileDataPageCount) }}" aria-label="Saved sections">{{ $mobilePercentage }}%</progress>
 <p>{{ $mobileSavedCount }} of {{ $mobileDataPageCount }} sections saved</p>
 @if($editable)
 @if(empty($qualityErrors))
 <span class="mobile-readiness is-ready">Ready for final review</span>
 @elseif((int)session('submission_checks_for') === $return->id)
 <section class="mobile-readiness-errors" aria-labelledby="mobile-readiness-error-title">
 <header><span class="mobile-error-symbol" aria-hidden="true">!</span><div><h3 id="mobile-readiness-error-title">{{ count($qualityErrors) }} {{ Str::plural('error',count($qualityErrors)) }} to fix</h3><p>Please complete these items to submit</p></div></header>
 <ul>@foreach($qualityErrors as $issueKey=>$issue)
 @php($issueSection = array_key_exists($issueKey,$sections) ? $issueKey : explode('_',$issueKey)[0])
 @php($issueSection = $issueSection === 'religion' ? 'enrolment' : $issueSection)
 @php($issueSection = str_starts_with($issueKey,'facility_') ? 'facilities' : $issueSection)
 <li>@if(array_key_exists($issueSection,$sections))<a href="{{ route('returns.form.show',[$return,$issueSection]) }}"><span>{{ $issue }}</span><span class="mobile-error-action">Fix &rarr;</span></a>@else<span>{{ $issue }}</span>@endif</li>
 @endforeach</ul>
 </section>
 @endif
 @endif

</div>
<nav class="source-form-tabs" aria-label="Source form sections">@foreach($sections as $key => $item)
<a class="{{ (array_key_exists($key,$return->statistical_form ?? []) && ($key !== 'enrolment' || array_key_exists('religion',$return->statistical_form ?? []))) ? 'is-section-saved' : '' }}" href="{{ route('returns.form.show', [$return, $key]) }}" @if($key === $sectionKey) aria-current="page" @endif>
@if((array_key_exists($key,$return->statistical_form ?? []) && ($key !== 'enrolment' || array_key_exists('religion',$return->statistical_form ?? []))))<span class="reference-saved-check" aria-label="Section saved">&#10003;</span>@endif
<span>{{ $item['title'] }}</span></a>
@endforeach</nav>
<div class="reference-progress-caption">{{ $mobileSavedCount }} of {{ $mobileDataPageCount }} sections saved</div>
</section><div class="source-entry-content {{ $sectionKey === 'identity' ? 'profile-capture-inline' : '' }}">
    <section class="card reference-form-card"><header class="card-header reference-form-header"><h2>{{ $sectionKey === 'identity' ? 'School profile' : $section['title'] }}</h2>@unless($editable)<span>Read-only submission</span>@endunless</header>
        <form data-protect-changes @if($editable) data-autosave-url="{{ route('returns.form.autosave', [$return, $sectionKey]) }}" data-autosave-key="{{ auth()->id() }}-{{ $return->id }}-{{ $sectionKey }}" data-draft-saved-at="{{ $draftSavedAt }}" @endif method="POST" action="{{ route('returns.form.save', [$return, $sectionKey]) }}" class="resource-form source-form">@csrf
            <input type="hidden" name="draft_revision" value="{{ $draftRevision }}">
            @if($editable)<div class="form-autosave-status" data-autosave-status role="status" aria-live="polite" hidden></div>@endif
            @if(!empty($section['description']))<p class="questionnaire-source-description">{{ $section['description'] }}</p>@endif
            <input type="hidden" name="data[_section]" value="{{ $sectionKey }}">
            @if($sectionKey === 'academic' && empty($section['tables']))<p class="academic-not-applicable">The examinations on this statistical form do not apply to your school level. You can continue to the next section.</p>@endif
            <?php
                $fieldGroups = $sectionKey === 'identity'
                    ? collect($section['fields'] ?? [])->groupBy(fn($field, $key) => in_array($key, ['level','funding','gender','boarding']) ? $section['classification_title'] : '', true)
                    : collect(['' => $section['fields'] ?? []]);
            ?>
            @foreach($fieldGroups as $groupTitle => $groupFields)
            @if(count($groupFields))
            @if($groupTitle)<fieldset class="source-field-group {{ $sectionKey === 'identity' && isset($section['classification_title']) && $groupTitle === $section['classification_title'] ? 'school-classification-fields' : '' }}"><legend>{{ $groupTitle }}</legend>@endif
            <div class="form-grid">@foreach($groupFields as $key => $field)<div class="source-field source-field-{{ $key }}" @if($sectionKey === 'utilities' && $key === 'water_explanation' && old('data.water_reliable', $data['water_reliable'] ?? null) !== 'No') hidden @endif><label for="control-{{ md5('data['.$key.']') }}"><span class="source-question-text">{{ $field['display_label'] ?? $field['label'] }}</span>@if(\App\Support\StatisticalForm::requiredForSubmission($sectionKey, $key))<x-ui.required-mark/>@endif</label><x-ui.source-field :field="$field" :name="'data['.$key.']'" :value="old('data.'.$key, $data[$key] ?? null)" :disabled="!$editable || in_array($key,$confirmedKeys ?? [])" /></div>@endforeach</div>
            @if($groupTitle)</fieldset>@endif
            @endif
            @endforeach
            @foreach($section['tables'] ?? [] as $tableKey => $table)
                <?php
                    $rows = old('data.'.$tableKey, $data[$tableKey] ?? []);
                    $rowLabels = $table['rows'] ?? array_fill_keys(array_keys($rows ?: [0 => []]), '');
                    $numericColumns = ($table['show_totals'] ?? false) ? collect($table['columns'])->filter(fn($f) => in_array($f['type'], ['integer']))->keys()->all() : [];
                ?>
                @if($sectionKey === 'enrolment' && $tableKey !== 'community')
                @include('returns.partials.paper-enrolment')
                @elseif($tableKey === 'community')
                @include('returns.partials.religious-affiliation')
                @else
                <section data-source-table="{{ $tableKey }}" @if(!isset($table['rows'])) data-repeatable-table @endif>@if($table['label'] !== $section['title'])<h3>{{ $table['label'] }}</h3>@endif @if(!empty($table['caption']))<h4 class="questionnaire-table-caption">{{ $table['caption'] }}</h4>@endif<div class="table-wrap"><table class="data-table source-data-table {{ $sectionKey === 'facilities' ? 'facility-readable-table' : ($tableKey === 'members' ? 'staff-entry-table' : ($tableKey === 'programmes' ? 'study-entry-table' : '')) }}">@if($tableKey === 'programmes')<colgroup><col style="width:30px">@foreach($table['columns'] as $studyColumn)<col>@endforeach @if($editable)<col style="width:76px">@endif</colgroup>@endif<thead><tr><th scope="col">{{ $table['row_heading'] ?? 'No.' }}</th>@foreach($table['columns'] as $columnKey => $column)<th scope="col" class="source-col-{{ $columnKey }}">@if($tableKey === 'members' && $columnKey === 'gender')<span class="staff-gender-full">{{ $column['display_label'] ?? $column['label'] }}</span><span class="staff-gender-short">Gender</span>@else{{ $column['display_label'] ?? $column['label'] }}@endif @if(\App\Support\StatisticalForm::requiredForSubmission($sectionKey, $columnKey, $tableKey))<x-ui.required-mark/>@endif</th>@endforeach @if($numericColumns)<th scope="col">{{ $table['total_label'] ?? 'Total' }}</th>@endif @if(!isset($table['rows']) && $editable)<th scope="col">Actions</th>@endif</tr></thead><tbody data-source-rows>
                    @foreach($rowLabels as $rowKey => $label)
                        <tr><th scope="row">{{ $label ?: $loop->iteration }}</th>@foreach($table['columns'] as $columnKey => $column)<td class="source-col-{{ $columnKey }}"><x-ui.source-field :field="array_merge($column, ['label' => ($label ?: $loop->iteration).' - '.$column['label']])" :name="'data['.$tableKey.']['.$rowKey.']['.$columnKey.']'" :value="$rows[$rowKey][$columnKey] ?? null" :disabled="!$editable" />@if($columnKey === 'remarks' && isset($table['row_notes'][$rowKey]))<small class="questionnaire-source-note">{{ $table['row_notes'][$rowKey] }}</small>@endif</td>@endforeach
                        @if($numericColumns)<td class="source-total">{{ collect($numericColumns)->sum(fn($column) => (int)($rows[$rowKey][$column] ?? 0)) }}</td>@endif
                        @if(!isset($table['rows']) && $editable)<td><button type="button" class="button" data-remove-source-row>Remove</button></td>@endif</tr>
                    @endforeach
                    </tbody>@if($numericColumns)<tfoot><tr><th>{{ $table['total_label'] ?? 'Total' }}</th>@foreach($table['columns'] as $columnKey => $column)<td>{{ in_array($columnKey, $numericColumns) ? collect($rows)->sum($columnKey) : '' }}</td>@endforeach<td>{{ collect($rows)->sum(fn($row) => collect($numericColumns)->sum(fn($column) => (int)($row[$column] ?? 0))) }}</td>@if(!isset($table['rows']) && $editable)<td></td>@endif</tr></tfoot>@endif
                </table></div>
                @if(!isset($table['rows']) && $editable)<button type="button" class="button button-secondary" data-add-source-row>Add row</button>@endif
                @if($numericColumns)<p class="muted">Totals reflect the saved values. Save this section to recalculate.</p>@endif
                </section>
                @endif
            @endforeach
            @if(!empty($section['continuation_title']) && count($data['members'] ?? []) > 17)<h3>{{ $section['continuation_title'] }}</h3>@endif
            @if(!empty($section['note']))<p class="questionnaire-source-note">{{ $section['note'] }}</p>@endif
            @if($editable)<div class="form-action-error" data-form-action-error role="alert" tabindex="-1" @if(!$errors->any()) hidden @endif>{{ $errors->first() }}</div><div class="form-actions source-save-bar"><div class="mobile-form-bottom-actions"><div class="mobile-three-actions">@if($mobileSectionPosition > 0)<button class="button" type="submit" name="navigate" value="previous">Previous</button>@endif
@if($sectionKey === 'studies')
<button class="button" type="submit">Save draft</button><button class="button button-primary review-confirm-button" type="submit" name="navigate" value="next">Review and confirm</button>
@else
@if($mobileSectionPosition < count($sections)-1)<button class="button" type="submit" name="navigate" value="next">Next</button>@endif
<button class="button button-primary" type="submit">Save draft</button>
@endif</div></div></div>@endif
@if(!$editable)<div class="mobile-only-layout mobile-bottom-navigation">@if($mobileSectionPosition > 0)<a class="button" href="{{ route('returns.form.show',[$return,$mobileSectionKeys[$mobileSectionPosition-1]]) }}">&larr; Previous</a>@endif @if($mobileSectionPosition < count($sections)-1)<a class="button" href="{{ route('returns.form.show',[$return,$mobileSectionKeys[$mobileSectionPosition+1]]) }}">Next &rarr;</a>@endif</div>@endif
        </form>
    </section>
    @if(auth()->user()->isSchoolRepresentative())
        @if($return->correction_reason)<div class="alert"><strong>Corrections requested</strong><p>{{ $return->correction_reason }}</p></div>@endif
    @endif
</div></div></div>
</x-layouts.app>
