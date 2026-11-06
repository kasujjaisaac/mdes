<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions"><a class="button" href="{{ route('reporting-periods.show',$period) }}">Back to window</a></div></x-slot:topbarFilters>
<section class="card"><h1>Edit {{ $period->year }} submission window</h1><form data-protect-changes method="POST" action="{{ route('reporting-periods.update',$period) }}" class="resource-form">@csrf @method('PUT')
<div class="form-grid"><label>Title<input name="title" required value="{{ old('title',$period->title) }}"></label><label>Status<select name="status">
@foreach(['draft','open','closed','locked'] as $status)
<option value="{{ $status }}" @selected(old('status',$period->status)===$status)>{{ ucfirst($status) }}</option>
@endforeach
</select></label>
@foreach(['opening_date'=>'Opening date','closing_date'=>'Closing date','extension_date'=>'Extended deadline'] as $key=>$label)
<label>{{ $label }}<input type="date" name="{{ $key }}" value="{{ old($key,$period->$key?->format('Y-m-d')) }}" @required($key!=='extension_date')></label>
@endforeach
</div><div class="form-actions"><button class="button button-primary">Save window</button></div></form></section></x-layouts.app>
