<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\SchoolReturn;
use App\Services\StatisticalFormDraftService;
use App\Services\StatisticalFormQualityService;
use App\Services\StatisticalFormSummaryService;
use App\Services\SubmissionVersionService;
use App\Support\StatisticalForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StatisticalFormController extends Controller
{
    public function introduction(SchoolReturn $return)
    {
        Gate::authorize('view', $return);

        return view('returns.introduction', [
            'title' => 'STATISTICAL FORM FOR ALL SCHOOLS, 2026', 'return' => $return,
            'introduction' => json_decode(file_get_contents(resource_path('forms/statistical-2026-introduction.json')), true, 512, JSON_THROW_ON_ERROR),
        ]);
    }

    public function source(SchoolReturn $return)
    {
        Gate::authorize('view', $return);

        return response()->file(resource_path('forms/statistical-2026-source.pdf'));
    }

    public function show(SchoolReturn $return, string $section = 'identity')
    {
        Gate::authorize('view', $return);
        if (auth()->user()->isAdministrator()) {
            $return = app(SubmissionVersionService::class)->forDisplay($return);
            if ($return->status === 'returned_for_correction' && ! $return->getRelation('displayVersion')) {
                return redirect()->route('returns.show', $return);
            }
        }
        if (auth()->user()->isSchoolRepresentative() && ! $return->profile_confirmed_at && in_array($return->status, ['draft', 'returned_for_correction'])) {
            return redirect()->route('representative.confirm', $return);
        }
        if ($section === 'religion') {
            $section = 'enrolment';
        }
        $sections = StatisticalForm::sectionsForReturn($return);
        abort_unless(isset($sections[$section]), 404);
        $return->loadMissing('school.parish', 'school.district');
        $defaults = $section === 'identity' ? [
            'school_name' => $return->school->official_name,
            'registration_number' => $return->school->ministry_registration_number,
            'emis_code' => $return->school->emis_number,
            'parish' => $return->school->parish?->name,
            'district' => $return->school->district?->name,
        ] : [];
        $editable = Gate::allows('update', $return) && in_array($return->status, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]);
        if ($section === 'declaration') {
            $qualityErrors = app(StatisticalFormQualityService::class)->errors($return);
            $reviewFingerprint = StatisticalForm::reviewFingerprint($return);
            if ($editable) {
                $displayForm = $return->statistical_form ?? [];
                foreach ($return->statistical_form_drafts ?? [] as $key => $draft) {
                    if (isset($draft['data'])) {
                        $displayForm[$key] = $draft['data'];
                    }
                }
                if (isset($displayForm['enrolment']['community'])) {
                    $displayForm['religion']['community'] = $displayForm['enrolment']['community'];
                }
                $return->statistical_form = $displayForm;
            }

            return view('returns.submitted', [
                'title' => 'Review and submit', 'return' => $return->loadMissing('school', 'reportingPeriod'),
                'reviewMode' => $editable, 'qualityErrors' => $qualityErrors, 'reviewFingerprint' => $reviewFingerprint,
            ]);
        }
        $draft = $editable ? ($return->statistical_form_drafts[$section] ?? []) : [];
        $sectionData = $draft['data'] ?? $return->statistical_form[$section] ?? $defaults;
        if ($section === 'enrolment' && ! array_key_exists('community', $sectionData)) {
            $sectionData['community'] = $return->statistical_form_drafts['religion']['data']['community'] ?? $return->statistical_form['religion']['community'] ?? [];
        }

        return view('returns.statistical-form', [
            'title' => 'STATISTICAL FORM FOR ALL SCHOOLS, 2026', 'return' => $return->loadMissing('school.parish', 'school.district'),
            'sections' => $sections, 'sectionKey' => $section, 'section' => $sections[$section],
            'data' => $section === 'identity' && auth()->user()->isSchoolRepresentative() ? [...$sectionData, ...($return->profile_snapshot ?? [])] : $sectionData,
            'draftRevision' => $draft['revision'] ?? 0, 'draftSavedAt' => $draft['saved_at'] ?? null,
            'confirmedKeys' => auth()->user()->isSchoolRepresentative() ? array_keys($return->profile_snapshot ?? []) : [],
            'qualityErrors' => app(StatisticalFormQualityService::class)->errors($return),
            'editable' => $editable,
        ]);
    }

    public function autosave(Request $request, SchoolReturn $return, string $section)
    {
        Gate::authorize('update', $return);
        $schema = ($section === 'religion' ? StatisticalForm::sections()['religion'] : (StatisticalForm::sectionsForReturn($return)[$section] ?? null)) ?? abort(404);
        $validated = $request->validate([...StatisticalForm::draftRules($schema), 'revision' => ['required', 'integer', 'min:0']]);
        abort_if(strlen(json_encode($validated['data'])) > 1000000, 413, 'This section is too large to save.');
        $result = DB::transaction(function () use ($return, $section, $validated) {
            $locked = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            abort_unless($locked->profile_confirmed_at, 409, 'Confirm your school profile before saving.');

            return app(StatisticalFormDraftService::class)->save($locked, $section, $validated['data'], (int) $validated['revision']);
        });

        return response()->json($result);
    }

    public function save(Request $request, SchoolReturn $return, string $section)
    {
        Gate::authorize('update', $return);
        if ($request->user()->isSchoolRepresentative() && ! $return->profile_confirmed_at) {
            return redirect()->route('representative.confirm', $return);
        }
        abort_unless(in_array($return->status, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]), 403);
        $schema = ($section === 'religion' ? StatisticalForm::sections()['religion'] : (StatisticalForm::sectionsForReturn($return)[$section] ?? null)) ?? abort(404);
        $request->validate(['draft_revision' => ['nullable', 'integer', 'min:0']]);
        $rules = StatisticalForm::rules($schema);
        if ($section === 'academic') {
            foreach (array_diff(array_keys(StatisticalForm::sections()['academic']['tables']), StatisticalForm::academicTables($return)) as $table) {
                $rules['data.'.$table] = ['prohibited'];
            }
        }
        if ($section === 'enrolment' && ! StatisticalForm::allowsNursery($return)) {
            $rules['data.nursery'] = ['prohibited'];
        }
        $validated = $request->validate($rules);
        $data = StatisticalForm::clean($validated['data'] ?? [], $schema);
        if ($section === 'identity' && $request->user()->isSchoolRepresentative()) {
            $data = [...$data, ...($return->profile_snapshot ?? [])];
        }
        foreach ($schema['tables'] ?? [] as $tableKey => $table) {
            if (! isset($table['repeatable'])) {
                continue;
            }
            $required = isset($table['columns']['name']) ? 'name' : 'grade';
            foreach ($data[$tableKey] ?? [] as $index => $row) {
                if (blank($row[$required] ?? null)) {
                    throw ValidationException::withMessages(["data.$tableKey.$index.$required" => ucfirst($required).' is required for every populated row.']);
                }
            }
        }
        DB::transaction(function () use ($return, $section, $data, $request) {
            $locked = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            abort_unless(in_array($locked->status, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]), 403);
            if ($request->user()->isSchoolRepresentative()) {
                abort_unless($locked->profile_confirmed_at, 409, 'Your school profile changed. Confirm it again before saving.');
                if ($section === 'identity') {
                    $data = [...$data, ...($locked->profile_snapshot ?? [])];
                }
            }
            $old = $locked->statistical_form ?? [];
            $drafts = $locked->statistical_form_drafts ?? [];
            $revision = (int) ($drafts[$section]['revision'] ?? 0);
            if ($request->filled('draft_revision')) {
                if ($request->integer('draft_revision') !== $revision) {
                    throw ValidationException::withMessages(['draft_revision' => 'This section was changed in another tab. Your entries have been kept. Reload and review the latest draft before continuing.']);
                }
            }
            $drafts[$section] = ['revision' => $revision + 1, 'saved_at' => now()->toIso8601String(), 'valid' => true];
            if ($section === 'enrolment' && $request->has('data.community')) {
                $drafts['religion'] = ['revision' => (int) ($drafts['religion']['revision'] ?? 0) + 1, 'saved_at' => now()->toIso8601String(), 'valid' => true];
            }
            if ($section === 'religion' && isset($drafts['enrolment'])) {
                $drafts['enrolment']['revision'] = (int) ($drafts['enrolment']['revision'] ?? 0) + 1;
                if (isset($drafts['enrolment']['data'])) {
                    $drafts['enrolment']['data']['community'] = $data['community'] ?? [];
                }
            }
            $locked->forceFill(['statistical_form' => [...$old, ...StatisticalForm::sectionPayloads($section, $data, $request->has('data.community'))], 'statistical_form_drafts' => $drafts])->save();
            app(StatisticalFormSummaryService::class)->sync($locked, $section, $data);
            app(RecordAuditEvent::class)->handle($request, 'statistical_form.saved', $locked, ['section' => $section], ['section' => $section]);
        });

        if (in_array($request->input('navigate'), ['next', 'previous'], true)) {
            $keys = array_keys(StatisticalForm::sectionsForReturn($return));
            if ($section === 'religion') {
                $section = 'enrolment';
            }
            $position = array_search($section, $keys, true);
            $offset = $request->input('navigate') === 'previous' ? -1 : 1;
            $section = $keys[$position + $offset] ?? $section;
        }

        return redirect()->route('returns.form.show', [$return, $section])->with('status', 'Form section saved.');
    }
}
