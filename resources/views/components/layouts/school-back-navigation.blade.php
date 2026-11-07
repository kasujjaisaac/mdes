@php
    $schoolBack = null;
    if (auth()->user()->isSchoolRepresentative() && !request()->routeIs('representative.home', 'dashboard')) {
        $schoolBack = ['href' => route('representative.home'), 'label' => 'Back to dashboard'];
        if (request()->routeIs('representative.profile.edit')) {
            $schoolBack = ['href' => route('representative.profile'), 'label' => 'Back to school profile'];
        } elseif (request()->routeIs('representative.confirm', 'returns.show', 'returns.form.show')) {
            $schoolBack = ['href' => route('returns.index'), 'label' => 'Back to submissions'];
        } elseif (request()->routeIs('returns.*') && !request()->routeIs('returns.index') && request()->route('return')) {
            $schoolBack = ['href' => route('returns.show', request()->route('return')), 'label' => 'Back to submission'];
        } elseif (request()->routeIs('reporting-periods.show')) {
            $schoolBack = ['href' => route('reporting-periods.index'), 'label' => 'Back to submission windows'];
        } elseif (request()->routeIs('profile.password.edit')) {
            $schoolBack = ['href' => route('profile.show'), 'label' => 'Back to my account'];
        }
    } elseif (request()->routeIs('schools.*')) {
        $schoolBack = request()->routeIs('schools.index')
            ? ['href' => route('dashboard'), 'label' => 'Back to dashboard']
            : ['href' => route('schools.index'), 'label' => 'Back to registered schools'];
    }
@endphp
@if($schoolBack)
    <nav class="school-page-back" aria-label="Back navigation">
        <x-ui.back-link :href="$schoolBack['href']" :label="$schoolBack['label']"/>
    </nav>
@endif
