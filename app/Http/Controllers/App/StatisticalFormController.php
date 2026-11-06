<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\SchoolReturn;
use App\Services\StatisticalFormSummaryService;
use App\Support\StatisticalForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StatisticalFormController extends Controller
{
    public function show(SchoolReturn $return, string $section = 'identity')
    {
        Gate::authorize('view', $return);
        if (auth()->user()->isSchoolRepresentative() && ! $return->profile_confirmed_at && in_array($return->status, ['draft', 'returned_for_correction'])) {
            return redirect()->route('representative.confirm', $return);
        }
        $sections = StatisticalForm::sections();
        abort_unless(isset($sections[$section]), 404);
        $return->loadMissing('school.parish', 'school.district');
        $defaults = $section === 'identity' ? [
            'school_name' => $return->school->official_name,
            'registration_number' => $return->school->ministry_registration_number,
            'emis_code' => $return->school->emis_number,
            'parish' => $return->school->parish?->name,
            'district' => $return->school->district?->name,
        ] : [];

        return view('returns.statistical-form', [
            'title' => 'Statistical Form 2026', 'return' => $return->loadMissing('school.parish', 'school.district'),
            'sections' => $sections, 'sectionKey' => $section, 'section' => $sections[$section],
            'data' => $section === 'identity' && auth()->user()->isSchoolRepresentative() ? [...($return->statistical_form[$section] ?? $defaults), ...($return->profile_snapshot ?? [])] : ($return->statistical_form[$section] ?? $defaults),
            'confirmedKeys' => auth()->user()->isSchoolRepresentative() ? array_keys($return->profile_snapshot ?? []) : [],
            'qualityErrors' => app(\App\Services\StatisticalFormQualityService::class)->errors($return),
            'editable' => Gate::allows('update', $return) && in_array($return->status, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]),
        ]);
    }

    public function save(Request $request, SchoolReturn $return, string $section)
    {
        Gate::authorize('update', $return);
        if ($request->user()->isSchoolRepresentative() && ! $return->profile_confirmed_at) {
            return redirect()->route('representative.confirm', $return);
        }
        abort_unless(in_array($return->status, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]), 403);
        $schema = StatisticalForm::sections()[$section] ?? abort(404);
        $validated = $request->validate(StatisticalForm::rules($schema));
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
            $locked->forceFill(['statistical_form' => [...$old, $section => $data]])->save();
            app(StatisticalFormSummaryService::class)->sync($locked, $section, $data);
            app(RecordAuditEvent::class)->handle($request, 'statistical_form.saved', $locked, ['section' => $section], ['section' => $section]);
        });

        if (in_array($request->input('navigate'), ['next', 'previous'], true)) {
            $keys = array_keys(StatisticalForm::sections());
            $position = array_search($section, $keys, true);
            $offset = $request->input('navigate') === 'previous' ? -1 : 1;
            $section = $keys[$position + $offset] ?? $section;
        }
        return redirect()->route('returns.form.show', [$return, $section])->with('status', 'Form section saved.');
    }
}
