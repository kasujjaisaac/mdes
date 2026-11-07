<?php
$portalSchool = auth()->user()->school;
$portalRouteReturn = request()->route('return');
$portalYear = filter_var(request()->query('year'), FILTER_VALIDATE_INT) ?: now('Africa/Kampala')->year;
$portalReturn = $portalRouteReturn instanceof \App\Models\SchoolReturn && $portalRouteReturn->school_id === auth()->user()->school_id
    ? $portalRouteReturn
    : \App\Models\SchoolReturn::where('school_id', auth()->user()->school_id)->whereHas('reportingPeriod', fn($query) => $query->where('year', $portalYear))->with('reportingPeriod')->latest('updated_at')->first();
$portalYear = $portalReturn?->reportingPeriod?->year ?? $portalYear;
$portalStatus = $portalReturn?->statusLabel() ?? 'Not started';
$portalStatusKey = $portalReturn?->status ?? 'not_started';
?>
<div class="school-portal-context">
    <div class="school-portal-identity"><span>School portal</span><strong title="{{ $portalSchool?->official_name }}">{{ $portalSchool?->official_name ?? 'Your school' }}</strong></div>
    <div class="school-portal-badges"><span class="school-reporting-badge"><x-ui.nav-icon name="calendar"/> {{ $portalYear }} reporting</span><a class="school-form-status is-{{ $portalStatusKey }}" href="{{ $portalReturn ? route('returns.form.show',[$portalReturn, $portalReturn->isLocked() ? 'declaration' : 'identity']) : route('returns.index') }}" aria-label="Form status: {{ $portalStatus }}"><span aria-hidden="true"></span>{{ $portalStatus }}</a></div>
</div>
