<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions"><a class="button" href="{{ route('returns.index') }}">Submissions</a></div></x-slot:topbarFilters>
<div class="submitted-record-page">
<header class="submitted-record-summary"><div class="submitted-record-year"><span>REPORTING YEAR</span><strong>{{ $return->reportingPeriod->year }}</strong></div><div class="submitted-record-school"><h1>{{ $return->school->official_name }}</h1><p>{{ $return->reportingPeriod->title }} &mdash; annual submission</p></div><div class="submitted-record-date"><span class="submission-status is-submitted"><x-ui.nav-icon name="checklist"/> Submitted</span>
@if($return->submitted_at)
<div class="submitted-date-value"><span>Date submitted</span><time datetime="{{ $return->submitted_at->toIso8601String() }}">{{ $return->submitted_at->timezone('Africa/Kampala')->format('d F Y, H:i') }}</time></div>
@else
<span class="muted">Submission date not recorded</span>
@endif
</div></header>
<nav class="submitted-record-nav" aria-label="Submitted data sections">
@foreach(\App\Support\StatisticalForm::sections() as $key=>$section)
<a href="#submitted-{{ $key }}">{{ preg_replace('/^[0-9\-]+\.\s*/','',$section['title']) }}</a>
@endforeach
</nav>
<?php $display = fn($value) => is_array($value) ? implode(', ',array_map(fn($v)=>is_scalar($v)?(string)$v:'', $value)) : (blank($value)?'Not recorded':(string)$value); ?>
@foreach(\App\Support\StatisticalForm::sections() as $key=>$section)
<section id="submitted-{{ $key }}" class="card submitted-data-section"><header class="submitted-section-heading"><span>{{ str_pad($loop->iteration,2,'0',STR_PAD_LEFT) }}</span><h2>{{ $section['title'] }}</h2></header><div class="submitted-section-body">
@if(!array_key_exists($key,$return->statistical_form??[]))<p>Not recorded</p>
@else
<?php $data=$return->statistical_form[$key]; ?>
@if(!empty($section['fields']))<table class="profile-values-table"><thead><tr><th>Field</th><th>Submitted value</th></tr></thead><tbody>
@foreach($section['fields'] as $fieldKey=>$field)<tr><th scope="row">{{ $key === 'identity' && $fieldKey === 'date' ? 'Date submitted' : $field['label'] }}</th><td>{{ $key === 'identity' && $fieldKey === 'date' ? ($return->submitted_at?->timezone('Africa/Kampala')->format('d F Y, H:i') ?? 'Submission date not recorded') : $display($data[$fieldKey]??null) }}</td></tr>
@endforeach</tbody></table>
@endif
@foreach($section['tables']??[] as $tableKey=>$table)<h3>{{ $table['label'] }}</h3><div class="table-wrap"><table class="profile-values-table"><thead><tr><th>Item</th>
@foreach($table['columns'] as $column)<th>{{ $column['label'] }}</th>
@endforeach</tr></thead><tbody>
@forelse(($table['rows']??array_fill_keys(array_keys($data[$tableKey]??[]),'')) as $rowKey=>$label)<tr><th scope="row">{{ $label ?: $loop->iteration }}</th>
@foreach($table['columns'] as $columnKey=>$column)<td>{{ $display($data[$tableKey][$rowKey][$columnKey]??null) }}</td>
@endforeach</tr>
@empty<tr><td colspan="{{ count($table['columns'])+1 }}">No entries recorded</td></tr>
@endforelse</tbody></table></div>
@endforeach
@endif
</div></section>
@endforeach
</div>
</x-layouts.app>
