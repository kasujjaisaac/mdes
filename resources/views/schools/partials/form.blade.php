@if($errors->any())<div class="alert school-form-errors" role="alert"><strong>Please check the highlighted fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<fieldset class="school-fieldset">@if(($compact ?? false) && request()->routeIs('representative.profile.edit'))<legend class="mobile-only-layout mobile-edit-legend">School identity</legend>@endif
@unless($compact ?? false)<legend><span>01</span> School identity</legend>@endunless
@unless($compact ?? false)<p>Use the school's official registered name and identifiers.</p>@endunless
@if(request()->routeIs('representative.*'))<p class="school-identity-help">The Secretariat manages the school name and recorded registration number and EMIS code. <a href="{{ route('support.index') }}">Contact support</a> for corrections.</p>@endif<div class="school-field-grid">
@foreach(['official_name'=>'Name of school','ministry_registration_number'=>'Registration number','emis_number'=>'EMIS code'] as $key=>$label)
<label class="{{ $key==='official_name'?'school-name-field':'' }}"><span class="school-capture-label">{{ $label }} @if(in_array($key,['official_name','ministry_registration_number']))<span class="required-mark">*</span>@endif</span><input name="{{ $key }}" @if(request()->routeIs('representative.*') && ($key==='official_name' || filled($school?->$key))) readonly @endif value="{{ request()->routeIs('representative.*') && filled($school?->$key) ? $school->$key : old($key, $school?->$key) }}" placeholder="{{ $key==='official_name'?'Enter the official school name':'Enter '.strtolower($label) }}" @required(in_array($key,['official_name','ministry_registration_number'])) @if($errors->has($key)) aria-invalid="true" @endif>@if($key==='official_name' && request()->routeIs('representative.confirm'))<small class="mobile-only-layout confirmation-name-help">Contact <a href="{{ route('support.index') }}">support</a> to correct the school name or official identifiers.</small>@endif
@error($key)<small class="school-field-error">{{ $message }}</small>@enderror</label>
@endforeach
</div></fieldset>
<fieldset class="school-fieldset">@if(($compact ?? false) && request()->routeIs('representative.profile.edit'))<legend class="mobile-only-layout mobile-edit-legend">Parish and district</legend>@endif
@unless($compact ?? false)<legend><span>02</span> Parish and district</legend>@endunless
@unless($compact ?? false)<p>Identify the school's Catholic parish and district.</p>@endunless<div class="school-field-grid">
<label><span class="school-capture-label">Catholic parish <span class="required-mark">*</span></span><select name="parish_id" required><option value="">Select a parish</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" @selected(old('parish_id',$school?->parish_id)==$parish->id)>{{ $parish->name }}</option>@endforeach</select>@error('parish_id')<small class="school-field-error">{{ $message }}</small>@enderror</label>
<label><span class="school-capture-label">District <span class="mobile-required-mark" aria-hidden="true">*</span></span><select name="district_id" data-mobile-required><option value="">Not recorded</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected(old('district_id',$school?->district_id)==$district->id)>{{ $district->name }}</option>@endforeach</select>@error('district_id')<small class="school-field-error">{{ $message }}</small>@enderror</label>
</div></fieldset>
<fieldset class="school-fieldset">@if(($compact ?? false) && request()->routeIs('representative.profile.edit'))<legend class="mobile-only-layout mobile-edit-legend">School classification</legend>@endif
@unless($compact ?? false)<legend><span>03</span> School classification</legend>@endunless
@unless($compact ?? false)<p>Select the classifications recorded on the statistical form.</p>@endunless<div class="school-field-grid school-classification-grid">
@foreach(['school_level'=>['Type of school',['nursery'=>'Pre-primary','primary'=>'Primary','secondary'=>'Secondary','vocational_technical'=>'Vocational / Technical','tertiary'=>'Tertiary']], 'funding_classification'=>['Government or private',['Government'=>'Government','Private'=>'Private']], 'gender_classification'=>['Gender classification',['Boys'=>'Boys','Girls'=>'Girls','Mixed'=>'Mixed']], 'boarding_status'=>['Day or boarding',['Day'=>'Day','Boarding'=>'Boarding','Both day and boarding'=>'Both day and boarding']]] as $key=>$definition)
<label><span class="school-capture-label">{{ $definition[0] }} @if($key==='school_level')<span class="required-mark">*</span>@else<span class="mobile-required-mark" aria-hidden="true">*</span>@endif</span><select name="{{ $key }}" data-mobile-required @required($key==='school_level')><option value="">Select</option>@foreach($definition[1] as $value=>$label)<option value="{{ $value }}" @selected(old($key,$school?->$key)===$value)>{{ $label }}</option>@endforeach</select>@error($key)<small class="school-field-error">{{ $message }}</small>@enderror</label>
@endforeach
</div></fieldset>

@unless(request()->routeIs('representative.confirm*'))
<fieldset class="school-fieldset"><legend>School head and contacts</legend><div class="school-field-grid">
@foreach(['headteacher_name'=>'Headteacher name','headteacher_phone'=>'Headteacher telephone','headteacher_email'=>'Headteacher email'] as $key=>$label)<label><span class="school-capture-label">{{ $label }}</span><input name="{{ $key }}" type="{{ str_contains($key,'email') ? 'email' : (str_contains($key,'phone') ? 'tel' : 'text') }}" value="{{ old($key, match($key){'headteacher_name'=>$school?->currentHeadteacher?->person_name,'headteacher_phone'=>$school?->currentHeadteacher?->telephone,default=>$school?->currentHeadteacher?->email}) }}">@error($key)<small class="school-field-error">{{ $message }}</small>@enderror</label>@endforeach
<label><span class="school-capture-label">School telephone</span><input name="contact[telephone]" type="tel" value="{{ old('contact.telephone',$school?->contact?->telephone) }}"></label>
<label><span class="school-capture-label">School email</span><input name="contact[email]" type="email" value="{{ old('contact.email',$school?->contact?->email) }}"></label>
</div></fieldset>

@endunless
