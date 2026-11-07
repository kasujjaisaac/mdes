<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportingPeriodRequest;
use App\Models\ReportingPeriod;
use App\Models\School;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReportingPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['search' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'in:draft,open,closed,locked'], 'year' => ['nullable', 'integer', 'between:2000,2100']]);

        return view('reporting-periods.index', [
            'title' => 'Submission Windows',
            'breadcrumbs' => [['label' => 'school statistics'], ['label' => 'reporting periods']],
            'actions' => [['label' => 'create reporting period', 'url' => route('reporting-periods.create')]],
            'periods' => ReportingPeriod::query()->withCount(['schools' => fn ($q) => $q->visibleTo(auth()->user()), 'schoolReturns' => fn ($q) => $q->whereHas('school', fn ($s) => $s->visibleTo(auth()->user())), 'schoolReturns as submitted_count' => fn ($q) => $q->whereNotNull('submitted_at')->whereHas('school', fn ($s) => $s->visibleTo(auth()->user()))])->when($request->filled('search'), fn ($q) => $q->where('title', 'like', '%'.$request->string('search')->toString().'%'))->when(in_array($request->input('status'), ['draft', 'open', 'closed', 'locked']), fn ($q) => $q->where('status', $request->input('status')))->when($request->filled('year'), fn ($q) => $q->where('year', $request->integer('year')))->latest()->paginate(15)->withQueryString(),
            'years' => ReportingPeriod::whereHas('schools', fn ($q) => $q->visibleTo(auth()->user()))->distinct()->orderByDesc('year')->pluck('year'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('reporting-periods.manage');

        return view('reporting-periods.create', [
            'title' => 'create reporting period',
            'breadcrumbs' => [
                ['label' => 'school statistics'],
                ['label' => 'reporting periods', 'url' => route('reporting-periods.index')],
                ['label' => 'create'],
            ],
            'schools' => School::query()->orderBy('official_name')->get(),
            'types' => ['annual_census'],
            'statuses' => [ReportingPeriod::STATUS_DRAFT, ReportingPeriod::STATUS_OPEN, ReportingPeriod::STATUS_CLOSED, ReportingPeriod::STATUS_LOCKED],
        ]);
    }

    public function store(StoreReportingPeriodRequest $request): RedirectResponse
    {
        try {
            $period = DB::transaction(function () use ($request) {
                $period = ReportingPeriod::create([...$request->safe()->except('school_ids'), 'created_by' => $request->user()->id]);
                $period->schools()->sync($request->validated('school_ids', []));
                app(RecordAuditEvent::class)->handle($request, 'reporting_period.created', $period, null, $period->toArray());

                return $period;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['year' => 'An annual submission window already exists for this year.']);
        }

        return redirect()->route('reporting-periods.show', $period)->with('status', 'reporting period created.');
    }

    public function edit(ReportingPeriod $reportingPeriod): View
    {
        Gate::authorize('reporting-periods.manage');
        abort_if($reportingPeriod->is_archived, 403);

        return view('reporting-periods.edit', ['title' => 'Edit submission window', 'period' => $reportingPeriod]);
    }

    public function update(Request $request, ReportingPeriod $reportingPeriod): RedirectResponse
    {
        Gate::authorize('reporting-periods.manage');
        abort_if($reportingPeriod->is_archived, 403);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'opening_date' => ['required', 'date'], 'closing_date' => ['required', 'date', 'after_or_equal:opening_date'], 'extension_date' => ['nullable', 'date', 'after_or_equal:closing_date'], 'status' => ['required', 'in:draft,open,closed,locked']]);
        DB::transaction(function () use ($request, $reportingPeriod, $data) {
            $locked = ReportingPeriod::whereKey($reportingPeriod->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->is_archived, 403);
            $before = $locked->only(array_keys($data));
            $locked->update($data);
            app(RecordAuditEvent::class)->handle($request, 'reporting_period.updated', $locked, $before, $locked->only(array_keys($data)));
        });

        return redirect()->route('reporting-periods.show', $reportingPeriod)->with('status', 'Submission window updated.');
    }

    public function show(ReportingPeriod $reportingPeriod): View
    {
        $visibleIds = School::visibleTo(auth()->user())->pluck('id');
        $returns = $reportingPeriod->schoolReturns()->whereIn('school_id', $visibleIds)->with('school')->latest()->get();

        return view('reporting-periods.show', [
            'title' => $reportingPeriod->title,
            'breadcrumbs' => [
                ['label' => 'school statistics'],
                ['label' => 'reporting periods', 'url' => route('reporting-periods.index')],
                ['label' => $reportingPeriod->title],
            ],
            'period' => $reportingPeriod->load(['schools' => fn ($q) => $q->whereIn('schools.id', $visibleIds)->with('parish')]),
            'returns' => $returns,
            'statusCounts' => $returns->countBy('status'),
        ]);
    }
}
