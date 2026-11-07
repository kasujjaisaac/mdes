<x-layouts.app :title="$title" :hide-heading="true">
<div class="reports-workspace"><x-ui.back-link :href="route('reports.index')" label="Back to reports"/>
<header class="reports-heading"><div><span class="diocesan-eyebrow">Report preview</span><h1>{{ $report['title'] }}</h1></div></header>
@if($errors->any())<div class="announcement-error" role="alert">{{ $errors->first() }}</div>@endif
<dl class="report-metadata">@foreach($report['metadata'] as $key=>$value)<div><dt>{{ $key }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
<div class="report-notes">@foreach($report['notes'] as $note)<p>{{ $note }}</p>@endforeach</div>
<form method="POST" action="{{ route('reports.export') }}" class="report-download-actions">@csrf @foreach($filters as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach<span>Download report</span>@foreach(\App\Support\Reports\ReportCatalog::FORMATS as $format=>$label)<button type="submit" class="button {{ $format==='xlsx'?'button-primary':'' }}" name="format" value="{{ $format }}">{{ $label }}</button>@endforeach</form>
@foreach($report['tables'] as $table)<section class="report-preview-table"><header><h2>{{ $table['name'] }}</h2><span>{{ number_format(count($table['rows'])) }} rows</span></header>@if($table['note'])<p>{{ $table['note'] }}</p>@endif
<div class="table-wrap"><table class="data-table"><thead><tr>@foreach($table['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead><tbody>@forelse(array_slice($table['rows'],0,15) as $row)<tr>@foreach($row as $value)<td>{{ $value===null?'Not reported':$value }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($table['headers']) }}">No records match this selection.</td></tr>@endforelse</tbody></table></div>@if(count($table['rows'])>15)<footer>Preview shows 15 of {{ number_format(count($table['rows'])) }} rows. Downloads include every row.</footer>@endif</section>@endforeach
</div></x-layouts.app>
