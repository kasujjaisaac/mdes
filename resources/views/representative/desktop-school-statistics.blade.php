@php
    $hasEnrolment = isset($schoolForm['enrolment']);
    $learnerTotal = $schoolBoys + $schoolGirls;
    $boysShare = $learnerTotal > 0 ? round($schoolBoys / $learnerTotal * 100, 1) : 0;
    $girlsShare = $learnerTotal > 0 ? round($schoolGirls / $learnerTotal * 100, 1) : 0;
    $teacherCount = isset($schoolForm['teaching']) ? count($schoolForm['teaching']['members'] ?? []) : null;
    $classrooms = $schoolForm['facilities']['items']['permanent_classrooms']['quantity'] ?? null;
    $resourceItems = [
        ['Library books', $schoolForm['facilities']['items']['library_books']['quantity'] ?? null, 'document'],
        ['Computers', $schoolForm['facilities']['items']['computers']['quantity'] ?? null, 'database'],
        ['Desk seating capacity', $schoolForm['facilities']['items']['desk_capacity']['quantity'] ?? null, 'school'],
    ];
@endphp
<section class="desktop-only-layout desktop-school-statistics" aria-label="School statistics breakdown">
    <header><h2>School at a glance</h2><p>Based on the selected saved form. Draft figures may change.</p></header>
    <div class="desktop-statistics-grid">
        <article class="desktop-statistics-panel">
            <header><x-ui.nav-icon name="users"/><h3>Learner breakdown</h3></header>
            @if($hasEnrolment)
            <div class="school-gender-values"><div><span><i class="gender-dot boys-dot" aria-hidden="true"></i>Boys</span><strong>{{ number_format($schoolBoys) }}</strong><small>{{ $boysShare }}% of learners</small></div><div><span><i class="gender-dot girls-dot" aria-hidden="true"></i>Girls</span><strong>{{ number_format($schoolGirls) }}</strong><small>{{ $girlsShare }}% of learners</small></div></div>
            @if($learnerTotal > 0)<div class="school-gender-bar" role="img" aria-label="Boys {{ $boysShare }} percent, girls {{ $girlsShare }} percent"><span style="width:{{ $boysShare }}%"></span><span style="width:{{ $girlsShare }}%"></span></div>@endif
            <p>{{ number_format($learnerTotal) }} learners recorded across class and nursery rows.</p>
            @else<p class="desktop-statistics-empty">Save the enrolment section to see the learner breakdown.</p>@endif
        </article>
        <article class="desktop-statistics-panel">
            <header><x-ui.nav-icon name="chart"/><h3>Teaching and classroom ratios</h3></header>
            <dl class="school-ratio-list"><div><dt>Learners per teacher</dt><dd @class(['is-missing' => !$hasEnrolment || !$teacherCount || $teacherCount <= 0])>{{ $hasEnrolment && $teacherCount > 0 ? number_format($learnerTotal / $teacherCount, 1) : 'Not available' }}</dd></div><div><dt>Learners per permanent classroom</dt><dd @class(['is-missing' => !$hasEnrolment || !$classrooms || $classrooms <= 0])>{{ $hasEnrolment && $classrooms > 0 ? number_format($learnerTotal / $classrooms, 1) : 'Not available' }}</dd></div></dl>
            <p>Calculated from recorded totals; these are averages, not actual class sizes.</p>
        </article>
        <article class="desktop-statistics-panel">
            <header><x-ui.nav-icon name="building"/><h3>Learning resources</h3></header>
            <dl class="school-resource-list">@foreach($resourceItems as [$label,$value,$icon])<div><dt><x-ui.nav-icon :name="$icon"/>{{ $label }}</dt><dd @class(['is-missing' => $value === null])>{{ $value !== null ? number_format($value) : 'Not recorded' }}</dd></div>@endforeach</dl>
            <p>Quantities from the facilities section of this form.</p>
        </article>
    </div>
</section>
