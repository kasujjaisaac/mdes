<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSchoolRequest;
use App\Http\Requests\UpdateSchoolRequest;
use App\Models\Diocese;
use App\Models\District;
use App\Models\EducationCluster;
use App\Models\Parish;
use App\Models\School;
use App\Models\SubCounty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public function index(Request $request): View
    {
        $schools = School::query()
            ->visibleTo($request->user())
            ->with(['parish', 'district', 'contact'])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($search) use ($request) {
                $term = '%'.$request->string('search')->toString().'%';
                $search->where('official_name', 'like', $term)->orWhere('emis_number', 'like', $term)->orWhere('ministry_registration_number', 'like', $term);
            }))
            ->when($request->filled('parish_id'), fn ($query) => $query->where('parish_id', $request->integer('parish_id')))
            ->when($request->filled('district_id'), fn ($query) => $query->where('district_id', $request->integer('district_id')))
            ->when($request->filled('school_level'), fn ($query) => $query->where('school_level', $request->string('school_level')->toString()))
            ->orderBy('official_name')
            ->paginate(15)
            ->withQueryString();

        return view('schools.index', [
            'title' => 'School Registry',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'schools'],
            ],
            'actions' => [
                ['label' => 'register school', 'url' => route('schools.create')],
            ],
            'schools' => $schools,
            'parishes' => Parish::query()->orderBy('name')->get(),
            'districts' => District::query()->orderBy('name')->get(),
            'levels' => $this->levels(),
            'ownerships' => $this->ownerships(),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('schools.manage');
        return view('schools.create', [
            'title' => 'register school',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'schools', 'url' => route('schools.index')],
                ['label' => 'register'],
            ],
            ...$this->formData(),
        ]);
    }

    public function store(StoreSchoolRequest $request, RecordAuditEvent $audit): RedirectResponse
    {
        $school = DB::transaction(function () use ($request, $audit): School {
            $school = School::query()->create([
                ...$request->safe()->except(['contact', 'status_reason']),
                'diocese_id' => Parish::findOrFail($request->integer('parish_id'))->diocese_id,
                'diocesan_code' => 'SYS-'.\Illuminate\Support\Str::uuid(),
                'ownership' => 'other',
                'operational_status' => School::STATUS_ACTIVE,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $school->contact()->create($request->validated('contact', []));
            $school->statusHistories()->create([
                'previous_status' => null,
                'new_status' => $school->operational_status,
                'effective_date' => now()->toDateString(),
                'reason' => $request->validated('status_reason', 'initial registration'),
                'responsible_user_id' => $request->user()->id,
            ]);

            $audit->handle($request, 'school.created', $school, null, $school->fresh()->toArray(), 'school registration');

            return $school;
        });

        return redirect()->route('schools.show', $school)->with('status', 'school registered.');
    }

    public function show(Request $request, School $school): View
    {
        Gate::authorize('view', $school);
        $request->validate(['return_id'=>['nullable','integer']]);
        $submissions=$school->schoolReturns()->with('reportingPeriod')->orderByDesc('submitted_at')->orderByDesc('id')->get();
        $selected=$request->filled('return_id') ? $submissions->firstWhere('id',$request->integer('return_id')) : $submissions->first();
        if ($request->filled('return_id') && !$selected) abort(404);
        $form=$selected?->statistical_form ?? [];
        return view('schools.show', [
            'title'=>$school->official_name,
            'school'=>$school->load(['parish','district']),
            'submissions'=>$submissions, 'selected'=>$selected, 'form'=>$form,
            'sections'=>\App\Support\StatisticalForm::sections(),
            'learnerTotal'=>isset($form['enrolment']) ? collect($form['enrolment']['classes']??[])->merge($form['enrolment']['nursery']??[])->sum(fn($r)=>(int)($r['boys']??0)+(int)($r['girls']??0)) : null,
        ]);
    }

    public function edit(School $school): View
    {
        Gate::authorize('update', $school);

        return view('schools.edit', [
            'title' => 'edit school',
            'breadcrumbs' => [
                ['label' => 'administration'],
                ['label' => 'schools', 'url' => route('schools.index')],
                ['label' => $school->official_name],
            ],
            'school' => $school->load('contact'),
            ...$this->formData(),
        ]);
    }

    public function update(UpdateSchoolRequest $request, School $school, RecordAuditEvent $audit): RedirectResponse
    {
        $oldValues = $school->load('contact')->toArray();

        DB::transaction(function () use ($request, $school, $audit, $oldValues): void {
            $previousStatus = $school->operational_status;
            $school->update([
                ...$request->safe()->except(['contact', 'status_reason']),
                'diocese_id' => Parish::findOrFail($request->integer('parish_id'))->diocese_id,
                'updated_by' => $request->user()->id,
            ]);
            $school->contact()->updateOrCreate(['school_id' => $school->id], $request->validated('contact', []));

            if ($previousStatus !== $school->operational_status) {
                $school->statusHistories()->create([
                    'previous_status' => $previousStatus,
                    'new_status' => $school->operational_status,
                    'effective_date' => now()->toDateString(),
                    'reason' => $request->validated('status_reason', 'status updated'),
                    'responsible_user_id' => $request->user()->id,
                ]);
            }

            $audit->handle($request, 'school.updated', $school, $oldValues, $school->fresh('contact')->toArray(), $request->validated('status_reason', null));
        });

        return redirect()->route('schools.show', $school)->with('status', 'school profile updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'dioceses' => Diocese::query()->orderBy('name')->get(),
            'parishes' => Parish::query()->orderBy('name')->get(),
            'districts' => District::query()->orderBy('name')->get(),
            'subCounties' => SubCounty::query()->with('district')->orderBy('name')->get(),
            'clusters' => EducationCluster::query()->orderBy('name')->get(),
            'levels' => $this->levels(),
            'ownerships' => $this->ownerships(),
            'statuses' => $this->statuses(),
        ];
    }

    private function levels(): array
    {
        return ['nursery', 'primary', 'secondary', 'vocational_technical', 'tertiary'];
    }

    private function ownerships(): array
    {
        return ['diocesan', 'government_aided', 'private', 'community', 'other'];
    }

    private function statuses(): array
    {
        return [
            School::STATUS_ACTIVE,
            School::STATUS_CLOSED,
            School::STATUS_MERGED,
            School::STATUS_SUSPENDED,
            School::STATUS_UNDER_CONSTRUCTION,
            School::STATUS_UNDER_REVIEW,
        ];
    }
}
