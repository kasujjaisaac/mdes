<?php

namespace App\Http\Controllers\App;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Parish;
use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Services\SchoolProfileContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RepresentativeController extends Controller
{
    public function home(Request $request)
    {
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        $school = $request->user()->school?->load('parish', 'district');
        $request->validate(['stats_return_id' => ['nullable', 'integer']]);
        $statisticReturns = $school ? $school->schoolReturns()->whereNotNull('statistical_form')->with('reportingPeriod')->get()->sortByDesc(fn ($r) => sprintf('%04d-%020d', $r->reportingPeriod?->year ?? 0, $r->id))->values() : collect();
        $statisticReturn = $request->filled('stats_return_id') ? $statisticReturns->firstWhere('id', $request->integer('stats_return_id')) : $statisticReturns->first();
        if ($request->filled('stats_return_id') && ! $statisticReturn) {
            abort(404);
        }
        $schoolForm = $statisticReturn?->statistical_form ?? [];
        $enrolmentRows = collect($schoolForm['enrolment']['classes'] ?? [])->merge($schoolForm['enrolment']['nursery'] ?? []);
        $schoolBoys = $enrolmentRows->sum(fn ($r) => (int) ($r['boys'] ?? 0));
        $schoolGirls = $enrolmentRows->sum(fn ($r) => (int) ($r['girls'] ?? 0));
        $schoolStatistics = [
            ['label' => 'Learners', 'icon' => 'users', 'value' => isset($schoolForm['enrolment']) ? $schoolBoys + $schoolGirls : null],
            ['label' => 'Teaching staff', 'icon' => 'staff', 'value' => isset($schoolForm['teaching']) ? count($schoolForm['teaching']['members'] ?? []) : null],
            ['label' => 'Support staff', 'icon' => 'users', 'value' => isset($schoolForm['support']) ? count($schoolForm['support']['members'] ?? []) : null],
            ['label' => 'Permanent classrooms', 'icon' => 'school', 'value' => $schoolForm['facilities']['items']['permanent_classrooms']['quantity'] ?? null],
            ['label' => 'Library books', 'icon' => 'document', 'value' => $schoolForm['facilities']['items']['library_books']['quantity'] ?? null],
            ['label' => 'Computers', 'icon' => 'building', 'value' => $schoolForm['facilities']['items']['computers']['quantity'] ?? null],
        ];

        return view('representative.home', ['title' => 'My School Dashboard', 'school' => $school, 'schoolStatistics' => $schoolStatistics, 'statisticReturn' => $statisticReturn, 'statisticReturns' => $statisticReturns, 'schoolForm' => $schoolForm, 'schoolBoys' => $schoolBoys, 'schoolGirls' => $schoolGirls,
            'periods' => $school ? ReportingPeriod::whereHas('schools', fn ($q) => $q->where('schools.id', $school->id))->with(['schoolReturns' => fn ($q) => $q->where('school_id', $school->id)])->orderByDesc('year')->orderByDesc('id')->get() : collect()]);
    }

    public function profile(Request $request)
    {
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        $school = $request->user()->school?->load('parish', 'district');
        if ($school) {
            Gate::authorize('view', $school);
        }

        return view('representative.profile', ['title' => 'School Profile', 'school' => $school]);
    }

    public function editProfile(Request $request)
    {
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        $school = $request->user()->school;
        abort_unless($school, 403);
        Gate::authorize('view', $school);

        return view('representative.edit-profile', ['title' => 'Edit School Profile', 'school' => $school, 'parishes' => $this->profileParishes($school)->orderBy('name')->get(), 'districts' => District::where('is_active', true)->orWhereKey($school->district_id)->orderBy('name')->get()]);
    }

    public function updateProfile(Request $request)
    {
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        $school = $request->user()->school;
        abort_unless($school, 403);
        Gate::authorize('view', $school);
        $request->merge(['ministry_registration_number' => is_string($request->input('ministry_registration_number')) ? School::normalizeMoeNumber($request->input('ministry_registration_number')) : $request->input('ministry_registration_number')]);
        $data = $request->validate([
            'headteacher_name' => ['nullable', 'string', 'max:255'], 'headteacher_phone' => ['nullable', 'string', 'max:40'], 'headteacher_email' => ['nullable', 'email', 'max:255'],
            'contact' => ['sometimes', 'array:telephone,email'], 'contact.telephone' => ['nullable', 'string', 'max:40'], 'contact.email' => ['nullable', 'email', 'max:255'],
            'parish_id' => ['required', 'integer', Rule::in($this->profileParishes($school)->pluck('id')->all())], 'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $school->district_id))],
            'ministry_registration_number' => ['required', 'string', 'max:80', ...($school->ministry_registration_number ? [Rule::in([$school->ministry_registration_number])] : []), Rule::unique('schools')->ignore($school->id), Rule::unique('users', 'username')->ignore($request->user()->id)],
            'emis_number' => ['nullable', 'string', 'max:60', ...($school->emis_number ? ['required', Rule::in([$school->emis_number])] : []), Rule::unique('schools')->ignore($school->id)],
            'school_level' => ['required', 'in:nursery,primary,secondary,vocational_technical,tertiary'],
            'funding_classification' => ['required', 'in:Government,Private'], 'gender_classification' => ['required', 'in:Boys,Girls,Mixed'],
            'boarding_status' => ['required', 'in:Day,Boarding,Both day and boarding'],
        ]);
        DB::transaction(function () use ($request, $school, $data) {
            $locked = School::whereKey($school->id)->lockForUpdate()->firstOrFail();
            $this->assertIdentifiersUnchanged($locked, $data);
            $before = $locked->toArray();
            $locked->update([...array_diff_key($data, array_flip(['headteacher_name', 'headteacher_phone', 'headteacher_email', 'contact'])), 'diocese_id' => Parish::findOrFail($data['parish_id'])->diocese_id, 'updated_by' => $request->user()->id]);
            app(SchoolProfileContactService::class)->sync($locked, $data);
            // Submitted snapshots remain immutable. Drafts must confirm the corrected profile.
            SchoolReturn::where('school_id', $school->id)->whereIn('status', ['draft', 'returned_for_correction'])->whereNull('submitted_at')->update(['profile_confirmed_at' => null, 'profile_confirmed_by' => null, 'profile_snapshot' => null]);
            app(RecordAuditEvent::class)->handle($request, 'school_profile.updated', $locked, $before, $locked->fresh()->toArray());
        });

        return redirect()->route('representative.profile')->with('status', 'School profile updated. Confirm the corrected details when continuing a draft form.');
    }

    public function confirm(Request $request, SchoolReturn $return)
    {
        Gate::authorize('view', $return);
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        if ($return->profile_confirmed_at) {
            return redirect()->route('returns.form.show', $return);
        }

        $school = $return->school;
        return view('representative.confirm', ['title' => 'Confirm School Profile', 'return' => $return, 'school' => $return->school, 'parishes' => $this->profileParishes($school)->orderBy('name')->get(), 'districts' => District::where('is_active', true)->orWhereKey($school->district_id)->orderBy('name')->get()]);
    }

    public function saveConfirmation(Request $request, SchoolReturn $return)
    {
        Gate::authorize('update', $return);
        abort_unless($request->user()->isSchoolRepresentative(), 403);
        $school = $return->school;
        $request->merge(['ministry_registration_number' => is_string($request->input('ministry_registration_number')) ? School::normalizeMoeNumber($request->input('ministry_registration_number')) : $request->input('ministry_registration_number')]);
        $data = $request->validate([
            'confirmed' => ['accepted'], 'parish_id' => ['required', 'integer', Rule::in($this->profileParishes($school)->pluck('id')->all())], 'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $school->district_id))],
            'ministry_registration_number' => ['required', 'string', 'max:80', ...($school->ministry_registration_number ? [Rule::in([$school->ministry_registration_number])] : []), Rule::unique('schools')->ignore($school->id), Rule::unique('users', 'username')->ignore($request->user()->id)],
            'emis_number' => ['nullable', 'string', 'max:60', ...($school->emis_number ? ['required', Rule::in([$school->emis_number])] : []), Rule::unique('schools')->ignore($school->id)],
            'school_level' => ['required', 'in:nursery,primary,secondary,vocational_technical,tertiary'],
            'funding_classification' => ['required', 'in:Government,Private'], 'gender_classification' => ['required', 'in:Boys,Girls,Mixed'],
            'boarding_status' => ['required', 'in:Day,Boarding,Both day and boarding'],
        ]);
        DB::transaction(function () use ($request, $return, $data, $school) {
            $school = School::whereKey($school->id)->lockForUpdate()->firstOrFail();
            $this->assertIdentifiersUnchanged($school, $data);
            $locked = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $locked);
            if ($locked->profile_confirmed_at) {
                return;
            }
            unset($data['confirmed']);
            $school->update([...$data, 'diocese_id' => Parish::findOrFail($data['parish_id'])->diocese_id, 'updated_by' => $request->user()->id]);
            $school->load('parish', 'district');
            $snapshot = ['school_name' => $school->official_name, 'registration_number' => $school->ministry_registration_number, 'emis_code' => $school->emis_number, 'parish' => $school->parish->name, 'district' => $school->district->name,
                'level' => match ($school->school_level) {
                    'nursery' => 'Pre-primary','primary' => 'Primary','secondary' => 'Secondary','tertiary' => 'Tertiary',default => 'Vocational/Technical'
                }, 'funding' => $school->funding_classification, 'gender' => $school->gender_classification, 'boarding' => $school->boarding_status];
            $snapshot += array_filter(['headteacher_name' => $school->currentHeadteacher?->person_name, 'headteacher_phone' => $school->currentHeadteacher?->telephone, 'first_deputy_name' => $school->currentFirstDeputy?->person_name, 'first_deputy_phone' => $school->currentFirstDeputy?->telephone, 'second_deputy_name' => $school->currentSecondDeputy?->person_name, 'second_deputy_phone' => $school->currentSecondDeputy?->telephone], fn ($value) => filled($value));
            $form = $locked->statistical_form ?? [];
            $form['identity'] = [...($form['identity'] ?? []), ...$snapshot];
            $locked->forceFill(['profile_confirmed_by' => $request->user()->id, 'profile_confirmed_at' => now(), 'profile_snapshot' => $snapshot, 'statistical_form' => $form])->save();
            app(RecordAuditEvent::class)->handle($request, 'school_profile.confirmed', $locked, null, ['school_id' => $school->id]);
        });

        return redirect()->route('returns.form.show', $return)->with('status', 'School profile confirmed. Continue with the form sections.');
    }
    private function profileParishes(School $school): \Illuminate\Database\Eloquent\Builder
    {
        return Parish::where(fn ($query) => $query->whereKey($school->parish_id)
            ->orWhere(fn ($active) => $active->where('is_active', true)->whereHas('diocese', fn ($diocese) => $diocese->where('code', 'MD'))));
    }

    private function assertIdentifiersUnchanged(School $school, array $data): void
    {
        foreach (['ministry_registration_number', 'emis_number'] as $key) {
            if (filled($school->$key) && ($data[$key] ?? null) !== $school->$key) {
                throw ValidationException::withMessages([$key => 'The school identifiers changed. Reload the profile and contact the Secretariat for corrections.']);
            }
        }
    }
}
