<x-layouts.app :title="$title" :breadcrumbs="$breadcrumbs" :hide-heading="true">
<x-slot:topbarFilters>
        <form method="GET" action="{{ route('schools.index') }}" class="registry-filters topbar-filters registry-topbar-filters" role="search">
            <label class="registry-search">Find a school<div><x-ui.nav-icon name="search" /><input type="search" name="search" value="{{ request('search') }}" placeholder="School name, registration or EMIS code"></div></label>
            <label>Parish<select name="parish_id"><option value="">All parishes</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" @selected(request('parish_id')==$parish->id)>{{ $parish->name }}</option>@endforeach</select></label>
            <label>District<select name="district_id"><option value="">All districts</option>@foreach($districts as $district)<option value="{{ $district->id }}" @selected(request('district_id')==$district->id)>{{ $district->name }}</option>@endforeach</select></label>
            <label>School level<select name="school_level"><option value="">All levels</option>@foreach($levels as $level)<option value="{{ $level }}" @selected(request('school_level')===$level)>{{ match($level){'nursery'=>'Pre-primary','vocational_technical'=>'Vocational',default=>ucfirst($level)} }}</option>@endforeach</select></label>
            <button class="button button-primary" type="submit">Search</button>
        @can('schools.manage')<a class="button button-primary" href="{{ route('schools.create') }}"><x-ui.nav-icon name="school" /> Register school</a>@endcan</form>
</x-slot:topbarFilters>
<div class="registry-page">
<section class="card registry-records">

        <div class="registry-results"><span><strong>{{ number_format($schools->total()) }}</strong> {{ $schools->total()===1?'school':'schools' }} found @if($schools->total())<small>Showing {{ $schools->firstItem() }}&ndash;{{ $schools->lastItem() }}</small>@endif</span>@if(request()->filled('search') || request()->filled('parish_id') || request()->filled('district_id') || request()->filled('school_level'))<a href="{{ route('schools.index') }}">Clear filters &times;</a>@else<span class="registry-results-note">Schools within your access</span>@endif</div>
        <div class="table-wrap"><table class="data-table registry-table registered-schools-table"><thead><tr><th scope="col">No.</th><th scope="col">School name</th><th scope="col">Registration number</th><th scope="col">Parish</th><th scope="col">District</th><th scope="col">Level</th><th scope="col">Funding</th><th scope="col">Residence</th><th scope="col">School head</th><th scope="col">Actions</th></tr></thead><tbody>
        @forelse($schools as $school)
            <tr><td class="registry-row-number">{{ $schools->firstItem() + $loop->index }}</td><td><div class="registry-school-cell"><div><a class="registry-school-name" href="{{ route('schools.show',$school) }}">{{ $school->official_name }}</a><small>EMIS: {{ $school->emis_number ?: 'Not recorded' }}</small></div></div></td><td>{{ $school->ministry_registration_number ?: 'Not recorded' }}</td><td><strong class="registry-parish">{{ $school->parish?->name ?? 'Not recorded' }}</strong></td><td>{{ $school->district?->name ?? 'Not recorded' }}</td><td><span class="registry-level">{{ match($school->school_level){'nursery'=>'Pre-primary','vocational_technical'=>'Vocational',default=>ucfirst(str_replace('_',' ',$school->school_level))} }}</span></td><td><span class="registry-funding {{ $school->funding_classification === 'Government' ? 'is-government' : ($school->funding_classification === 'Private' ? 'is-private' : '') }}">{{ $school->funding_classification ?: 'Not recorded' }}</span></td><td><span class="registry-boarding">{{ $school->boarding_status ?: 'Not recorded' }}</span></td><td>{{ $school->currentHeadteacher?->person_name ?: 'Not recorded' }}</td><td><div class="registry-row-actions"><button type="button" class="school-contact-button" data-message-url="{{ !auth()->user()->isSchoolRepresentative() && auth()->user()->hasPermission('returns.review') ? route('schools.message',$school) : '' }}" data-school-contact data-school="{{ $school->official_name }}" data-headteacher="{{ $school->currentHeadteacher?->person_name }}" data-telephone="{{ $school->contact?->telephone ?: $school->currentHeadteacher?->telephone }}" data-email="{{ $school->contact?->email ?: $school->currentHeadteacher?->email }}" aria-haspopup="dialog" aria-controls="school-contact-popup" aria-label="Contact {{ $school->official_name }}">Contact</button></div></td></tr>
        @empty
            <tr><td colspan="10"><div class="registry-empty"><x-ui.nav-icon name="school" /><h2>No schools found</h2><p>Try a different name or adjust your filters.</p><a class="button" href="{{ route('schools.index') }}">Reset search</a></div></td></tr>
        @endforelse
        </tbody></table></div>
        <div class="native-pagination">{{ $schools->links() }}</div>
    </section>
</div>
<dialog id="school-contact-popup" class="school-contact-popup" aria-labelledby="school-contact-title">
<header><h2 id="school-contact-title">School contacts</h2><button type="button" data-close-school-contact aria-label="Close contacts">&times;</button></header>
<strong data-contact-school></strong><p data-contact-headteacher></p>
<dl><div><dt>Phone</dt><dd><a data-contact-phone></a><span data-contact-phone-missing>Not recorded</span></dd></div><div><dt>Email</dt><dd><a data-contact-email></a><span data-contact-email-missing>Not recorded</span></dd></div></dl>
<a class="school-contact-message" data-contact-message hidden>Message school</a>
</dialog>
</x-layouts.app>
