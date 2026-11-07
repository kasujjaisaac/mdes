<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\DiocesanDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DiocesanDashboardService $analytics): View|RedirectResponse
    {
        if ($request->user()->isSchoolRepresentative()) {
            return redirect()->route('representative.home');
        }
        \Illuminate\Support\Facades\Gate::authorize('dashboard.view');
        $request->validate([
            'parish' => ['nullable', 'integer'], 'level' => ['nullable', 'string', 'max:80'], 'district' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'reporting_status' => ['nullable', 'in:submitted,outstanding'],
            'metric' => ['nullable', 'in:schools,learners,teaching,support,parishes,computers,water,seating'],
            'stage' => ['nullable', 'in:expected,started,submitted,outstanding'],
            'gender' => ['nullable', 'in:boys,girls'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $filterSchools = School::visibleTo($request->user())->with(['parish', 'district'])->orderBy('official_name')->get();
        $schools = $filterSchools->filter(fn ($s) => (! $request->filled('parish') || $s->parish_id == $request->integer('parish')) && (! $request->filled('level') || $s->school_level === $request->input('level')) && (! $request->filled('district') || $s->district_id == $request->integer('district')));
        $registeredSchoolCount = $schools->count();
        $ids = $schools->pluck('id');
        $periods = ReportingPeriod::whereHas('schools', fn ($q) => $q->whereIn('schools.id', $ids))->with(['schools' => fn ($q) => $q->whereIn('schools.id', $ids)])->get();
        $years = $periods->pluck('year')->map(fn ($y) => (int) $y)->unique()->sortDesc()->values();
        $year = $request->integer('year') ?: ($years->first() ?? now()->year);
        $years = $years->push($year)->unique()->sortDesc()->values();
        $currentPeriods = $periods->where('year', $year);
        $assigned = $currentPeriods->flatMap(fn ($p) => $p->schools->pluck('id'))->unique();
        $expected = $schools->whereIn('id', $assigned);
        // A return must belong to a window to which its school is actually assigned.
        $returns = SchoolReturn::whereIn('reporting_period_id', $currentPeriods->pluck('id'))->whereIn('school_id', $ids)
            ->with(['school.parish', 'reportingPeriod'])->latest('updated_at')->orderByDesc('id')->get()
            ->filter(fn ($r) => $currentPeriods->firstWhere('id', $r->reporting_period_id)?->schools->contains('id', $r->school_id))->unique('school_id')->values();
        if ($request->filled('reporting_status')) {
            $submittedIds = $returns->whereIn('status', SchoolReturn::SUBMITTED_STATUSES)->pluck('school_id');
            $expected = $expected->filter(fn ($school) => $request->input('reporting_status') === 'submitted' ? $submittedIds->contains($school->id) : ! $submittedIds->contains($school->id));
            $returns = $returns->whereIn('school_id', $expected->pluck('id'))->values();
        }
        $submittedCount = $returns->whereIn('status', SchoolReturn::SUBMITTED_STATUSES)->count();
        $recentSubmissions = $returns->whereIn('status', SchoolReturn::SUBMITTED_STATUSES)->sortByDesc(fn ($r) => $r->submitted_at?->getTimestamp() ?? 0)->take(6)->values();
        $captured = $returns->filter(fn ($r) => is_array($r->statistical_form) && count($r->statistical_form) > 0);
        $selected = $captured->filter(fn ($r) => in_array($r->status, SchoolReturn::SUBMITTED_STATUSES));
        $records = $selected->map(fn ($r) => $analytics->record($r))->keyBy(fn ($r) => $r['school']->id);
        $summary = $analytics->summary($records);
        $largestSchools = $records->whereNotNull('learners')->sortBy(fn ($r) => $r['school']->official_name)->sortByDesc('learners')->groupBy(fn ($r) => $r['school']->school_level)->sortKeys()->map(fn ($group) => $group->take(3)->values());
        $boys = $summary['boys']['value'];
        $girls = $summary['girls']['value'];
        $outstandingCount = $expected->count() - $submittedCount;
        $levelRows = $records->groupBy(fn ($r) => $r['school']->school_level)->map(fn ($group) => $analytics->summary($group));
        $parishRows = $expected->groupBy(fn ($s) => $s->parish_id ?? 'none')->map(function ($group) use ($returns) {
            $schoolIds = $group->pluck('id');

            return ['parish_id' => $group->first()->parish_id, 'name' => $group->first()->parish?->name ?? 'Unassigned', 'expected' => $group->count(),
                'submitted' => $returns->whereIn('school_id', $schoolIds)->whereIn('status', SchoolReturn::SUBMITTED_STATUSES)->count()];
        })->sortBy('name');
        if ($request->routeIs('dashboard.details')) {
            $request->validate(['metric' => ['nullable', 'in:schools,learners,teaching,water,seating']]);
            $metric = $request->input('metric', 'schools');
            $stage = $request->input('stage', 'expected');
            $gender = $request->input('gender');
            $labels = ['schools' => 'Schools', 'learners' => 'Learners served', 'teaching' => 'Teaching staff', 'water' => 'Schools with unreliable water', 'seating' => 'Desk seating shortfall', 'support' => 'Support staff', 'computers' => 'Computers', 'parishes' => 'Schools by parish'];
            $stageLabels = ['expected' => 'Expected schools', 'started' => 'Started returns', 'submitted' => 'Submitted / locked', 'outstanding' => 'Outstanding returns'];
            $detailRows = $expected->map(function ($school) use ($returns, $records, $currentPeriods, $metric, $stage, $gender) {
                $return = $returns->firstWhere('school_id', $school->id);
                $r = $records->get($school->id);
                $status = $return?->status ?? 'not_started';
                if ($metric === 'schools') {
                    $include = match ($stage) {
                        'started' => $return && $status !== 'not_started',
                        'submitted' => in_array($status, SchoolReturn::SUBMITTED_STATUSES),
                        'outstanding' => ! in_array($status, SchoolReturn::SUBMITTED_STATUSES),
                        default => true,
                    };
                    if (! $include) {
                        return null;
                    }
                    $value = null;
                } else {
                    if (! $r) {
                        return null;
                    }
                    $value = match ($metric) {
                        'learners' => $r[$gender ?? 'learners'], 'teaching' => $r['teachers'],
                        'water' => $r['water'] === 'No' ? 1 : null,
                        'seating' => $r['shortfall'], 'computers' => $r['computers'],
                        default => null,
                    };
                    if ($value === null) {
                        return null;
                    }
                    if ($metric === 'seating' && $value === 0) {
                        return null;
                    }
                }
                $period = $currentPeriods->first(fn ($p) => $p->schools->contains('id', $school->id));
                $section = match ($metric) {
                    'learners' => 'enrolment','teaching' => 'teaching','water' => 'utilities','seating','computers' => 'facilities',default => 'identity'
                };

                return ['school' => $school, 'value' => $value, 'status' => $status, 'submitted_at' => $return?->submitted_at, 'url' => $return ? route('returns.form.show', [$return, $section]) : route('reporting-periods.show', $period)];
            })->filter()->values();
            if ($metric === 'schools' && $stage === 'submitted') {
                $detailRows = $detailRows->sortByDesc(fn ($r) => $r['submitted_at']?->getTimestamp() ?? 0)->values();
            }
            $detailTotal = $detailRows->sum('value');
            $detailCount = $detailRows->count();
            $detailRows = new LengthAwarePaginator($detailRows->forPage($request->integer('page', 1), 20)->values(), $detailCount, 20, $request->integer('page', 1), ['path' => route('dashboard.details'), 'query' => $request->except('page')]);
            $title = $metric === 'schools' ? $stageLabels[$stage] : $labels[$metric].($gender ? ' ? '.ucfirst($gender) : '');

            return view('dashboard-details', compact('title', 'year', 'years', 'filterSchools', 'metric', 'stage', 'gender', 'detailRows', 'detailTotal', 'detailCount'));
        }

        return view('dashboard', compact('year', 'years', 'filterSchools', 'expected', 'registeredSchoolCount', 'summary', 'boys', 'girls', 'submittedCount', 'outstandingCount', 'levelRows', 'parishRows', 'recentSubmissions', 'largestSchools') + ['title' => 'Dashboard']);
    }
}
