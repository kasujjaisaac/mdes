<x-layouts.app :title="$title" :hide-heading="true">
<x-slot:topbarFilters><div class="profile-topbar-actions desktop-only-layout">@if($school)<a class="button button-primary" href="{{ route('representative.profile.edit') }}">Edit profile</a>@endif<a class="button" href="{{ route('representative.home') }}">Dashboard</a><a class="button button-primary" href="{{ route('returns.index') }}">Submissions &rarr;</a></div></x-slot:topbarFilters>


<div class="portal-profile-page mobile-only-layout">
@if($school)
<div class="portal-profile-actions"><a href="{{ route('representative.home') }}">&larr; Dashboard</a><a class="button button-primary" href="{{ route('representative.profile.edit') }}">Edit profile</a></div>
<table class="mobile-school-details" aria-label="School profile details"><tbody>
@foreach([
'School name'=>$school->official_name,
'Registration number'=>$school->ministry_registration_number,
'EMIS code'=>$school->emis_number,
'Catholic parish'=>$school->parish?->name,
'District'=>$school->district?->name,
'School level'=>match($school->school_level){'nursery'=>'Pre-primary','vocational_technical'=>'Vocational / Technical',default=>ucfirst(str_replace('_',' ',$school->school_level))},
'Funding'=>$school->funding_classification,
'Gender classification'=>$school->gender_classification,
'Day / boarding'=>$school->boarding_status,
'Operational status'=>ucfirst(str_replace('_',' ',$school->operational_status))
] as $label=>$value)
<tr><th scope="row">{{ $label }}</th><td class="{{ blank($value)?'is-missing':'' }}">{{ blank($value)?'Not recorded':$value }}</td></tr>
@endforeach
</tbody></table>
@else<section class="school-identity-empty"><x-ui.nav-icon name="school"/><h1>No school assigned</h1><p>Contact the administrator to link your account to a school.</p><a class="button" href="{{ route('representative.home') }}">Back to dashboard</a></section>@endif
</div><div class="desktop-only-layout school-identity-page compact-school-profile">@if($school)<div class="profile-details-sheet table-wrap"><table class="profile-values-table"><thead><tr><th>Field</th><th>Value</th></tr></thead><tbody>@foreach(['School name'=>$school->official_name,'Registration number'=>$school->ministry_registration_number,'EMIS code'=>$school->emis_number,'Catholic parish'=>$school->parish?->name,'District'=>$school->district?->name,'School level'=>ucfirst(str_replace('_',' ',$school->school_level)),'Government / private'=>$school->funding_classification,'Gender classification'=>$school->gender_classification,'Day / boarding'=>$school->boarding_status] as $label=>$value)<tr><th scope="row">{{ $label }}</th><td>{{ blank($value)?'Not recorded':$value }}</td></tr>@endforeach</tbody></table></div>@else<p>No school assigned</p>@endif</div></x-layouts.app>
