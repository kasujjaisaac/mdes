<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions"><a class="button" href="{{ route('reporting-periods.show',$period) }}">Back to window</a></div></x-slot:topbarFilters>
<section class="card">@if($errors->any())<div class="announcement-error" role="alert">{{ $errors->first() }}</div>@endif<h1>Edit {{ $period->year }} submission window</h1><form data-protect-changes method="POST" action="{{ route('reporting-periods.update',$period) }}" class="resource-form">@csrf @method('PUT')
<div class="form-grid"><label>Title<input name="title" required value="{{ old('title',$period->title) }}"></label><label>Status<select name="status">
@foreach(['draft','open','closed','locked'] as $status)
<option value="{{ $status }}" @selected(old('status',$period->status)===$status)>{{ ucfirst($status) }}</option>
@endforeach
</select></label>
@foreach(['opening_date'=>'Opening date','closing_date'=>'Closing date','extension_date'=>'Extended deadline'] as $key=>$label)
<label>{{ $label }}<input type="date" name="{{ $key }}" value="{{ old($key,$period->$key?->format('Y-m-d')) }}" @required($key!=='extension_date')></label>
@endforeach
</div><fieldset class="window-school-assignment"><legend>Participating schools</legend><p>Add newly registered schools to this window. Schools with submitted forms must remain selected.</p><input type="hidden" name="update_assignments" value="1"><div>@foreach($schools as $school)<label><input type="checkbox" name="school_ids[]" value="{{ $school->id }}" @checked(in_array($school->id,array_map('intval',old('school_ids',$assignedSchoolIds))))><span>{{ $school->official_name }}</span></label>@endforeach</div></fieldset><div class="form-actions"><button class="button button-primary">Save window</button></div></form></section></x-layouts.app>
