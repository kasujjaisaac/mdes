<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="school-profile-toolbar">
<header class="school-view-toolbar" aria-label="School profile controls">
    <div class="school-toolbar-identity">
        <a class="school-toolbar-back" href="{{ route('schools.index') }}" title="Back to School Registry" aria-label="Back to School Registry"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6M8 12h12"/></svg></a>
        <h1 title="{{ $school->official_name }}">{{ $school->official_name }}</h1>
    </div>
    <div class="school-toolbar-controls">
        <form method="GET" class="school-period-control" action="{{ route('schools.show',$school) }}">
            <label for="school-return-select">Reporting period</label>
            <select id="school-return-select" name="return_id" @disabled($submissions->isEmpty())>
                @foreach($submissions as $submission)<option value="{{ $submission->id }}" @selected($selected?->id===$submission->id)>{{ $submission->reportingPeriod?->year }} &middot; {{ $submission->reportingPeriod?->title }} ({{ str_replace('_',' ',$submission->status) }})</option>@endforeach
                @if($submissions->isEmpty())<option>No returns available</option>@endif
            </select>
            <button class="school-toolbar-button" type="submit" @disabled($submissions->isEmpty())>View</button>
        </form>
        <div class="school-toolbar-links">
            @can('update',$school)<a class="school-toolbar-button" href="{{ route('schools.edit',$school) }}"><x-ui.nav-icon name="document" /> Edit school</a>@endcan
            @if($selected)<a class="school-toolbar-button is-primary" href="{{ route('returns.show',$selected) }}"><x-ui.nav-icon name="clipboard" /> Open submission <span aria-hidden="true">&rarr;</span></a>@endif
        </div>
    </div>
</header>

</div></x-slot:topbarFilters>
<div class="school-profile-page">

@if($selected)
<div class="school-submission-summary"><div><span>Return status</span><strong class="school-return-status status-{{ $selected->status }}">{{ ucfirst(str_replace('_',' ',$selected->status)) }}</strong></div><div><span>Learners</span><strong>{{ $learnerTotal===null?'Not recorded':number_format($learnerTotal) }}</strong></div><div><span>Teaching staff</span><strong>{{ isset($form['teaching'])?count($form['teaching']['members']??[]):'Not recorded' }}</strong></div><div><span>Support staff</span><strong>{{ isset($form['support'])?count($form['support']['members']??[]):'Not recorded' }}</strong></div><div><span>Sections saved</span><strong>{{ count(array_intersect_key($form,$sections)) }} <small>/ {{ count($sections) }}</small></strong></div></div>
@if(in_array($selected->status,['draft','not_started','returned_for_correction']))<p class="school-profile-notice">This return is a draft or awaits correction. The figures below have not been submitted as final data.</p>@endif
@endif
<div class="school-profile-layout"><nav class="school-profile-nav" aria-label="School data sections"><span>FORM SECTIONS</span>@foreach($sections as $key=>$section)<a href="#school-section-{{ $key }}">{{ $section['title'] }}<small class="{{ array_key_exists($key,$form)?'section-saved':'section-missing' }}">{{ array_key_exists($key,$form)?'Saved':'Not recorded' }}</small></a>@endforeach<a href="#school-registry-record">Registry details</a></nav>
<div class="school-profile-content">
@foreach($sections as $sectionKey=>$section)
<?php $data=$form[$sectionKey]??[]; $saved=array_key_exists($sectionKey,$form); ?>
<details class="school-data-section" id="school-section-{{ $sectionKey }}" @if($loop->first) open @endif><summary><div><h2>{{ $section['title'] }}</h2><span>Source form ? page {{ $section['page'] }}</span></div><span class="school-section-state">{{ $saved?'Saved':'Not recorded' }} <b aria-hidden="true">+</b></span></summary><div class="school-data-body">
@if(!$saved)<p class="school-section-empty">No data recorded for this section in the selected return.</p>
@else
@if(isset($section['fields']))<dl class="school-data-fields">@foreach($section['fields'] as $key=>$field)<?php $value=$data[$key]??null; ?><div><dt>{{ $field['label'] }}</dt><dd class="{{ $value===null || $value==='' || $value===[] ? 'is-unrecorded':'' }}">{{ is_array($value)?(count($value)?implode(', ',$value):'Not recorded'):($value===null || $value===''?'Not recorded':$value) }}</dd></div>@endforeach</dl>@endif
@foreach($section['tables']??[] as $tableKey=>$table)
<?php $rows=$data[$tableKey]??[]; $rowLabels=$table['rows']??array_fill_keys(array_keys($rows),''); $numeric=collect($table['columns'])->filter(fn($c)=>$c['type']==='integer')->keys()->all(); ?>
<div class="school-data-table-block"><h3>{{ $table['label'] }}</h3><div class="table-wrap"><table class="data-table school-profile-table"><thead><tr><th scope="col">{{ isset($table['rows'])?'Item':'#' }}</th>@foreach($table['columns'] as $column)<th scope="col">{{ $column['label'] }}</th>@endforeach</tr></thead><tbody>@forelse($rowLabels as $rowKey=>$label)<tr><th scope="row">{{ $label ?: $loop->iteration }}</th>@foreach($table['columns'] as $columnKey=>$column)<?php $value=$rows[$rowKey][$columnKey]??null; ?><td class="{{ $value===null || $value==='' ? 'is-unrecorded':'' }}">{{ $value===null || $value===''?'Not recorded':$value }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($table['columns'])+1 }}">No entries recorded.</td></tr>@endforelse</tbody>@if($numeric && count($rows))<tfoot><tr><th>Total recorded</th>@foreach($table['columns'] as $columnKey=>$column)<td>{{ in_array($columnKey,$numeric)?number_format(collect($rows)->sum($columnKey)):'' }}</td>@endforeach</tr></tfoot>@endif</table></div></div>
@endforeach
@if($sectionKey==='declaration')<p class="school-profile-notice">Signature and stamp references identify the signed original; they are not electronic signatures.</p>@endif
@endif
</div></details>
@endforeach
<details class="school-data-section" id="school-registry-record"><summary><div><h2>Registry details</h2><span>Master school record, separate from the selected return</span></div><span aria-hidden="true">+</span></summary><div class="school-data-body"><dl class="school-data-fields">@foreach(['official_name'=>'School name','ministry_registration_number'=>'Registration number','emis_number'=>'EMIS code','school_level'=>'School level','funding_classification'=>'Government / private','gender_classification'=>'Gender classification','boarding_status'=>'Day / boarding'] as $key=>$label)<div><dt>{{ $label }}</dt><dd>{{ str_replace('_',' ',$school->$key ?? 'Not recorded') }}</dd></div>@endforeach<div><dt>Catholic parish</dt><dd>{{ $school->parish?->name ?? 'Not recorded' }}</dd></div><div><dt>District</dt><dd>{{ $school->district?->name ?? 'Not recorded' }}</dd></div></dl></div></details>
</div></div></div>
</x-layouts.app>
