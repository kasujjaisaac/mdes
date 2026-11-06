<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterSchoolRequest;
use App\Models\Diocese;
use App\Models\District;
use App\Models\Parish;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolProfileContactService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolRegistrationController extends Controller
{
    public function create()
    {
        return view('auth.register-school', [
            'parishes' => Parish::whereHas('diocese', fn ($q) => $q->where('code', 'MD'))->where('is_active', true)->orderBy('name')->get(),
            'districts' => District::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(RegisterSchoolRequest $request, RecordAuditEvent $audit)
    {
        try {
            $user = DB::transaction(function () use ($request, $audit) {
                $data = $request->validated();
                $data['registration_date'] = now('Africa/Kampala')->toDateString();
                $parish = Parish::whereKey($data['parish_id'])->lockForUpdate()->firstOrFail();
                if (School::where('parish_id', $parish->id)->whereRaw('LOWER(official_name) = ?', [mb_strtolower($data['official_name'])])->exists()) {
                    throw ValidationException::withMessages(['official_name' => 'This school is already registered in the selected parish. Sign in or contact the Secretariat for account access.']);
                }
                $role = Role::where('slug', 'headteacher')->firstOrFail();
                $school = School::create([
                    ...$request->safe()->only(['official_name', 'parish_id', 'district_id', 'school_level', 'ministry_registration_number', 'emis_number', 'funding_classification', 'gender_classification', 'boarding_status']),
                    'registration_date' => $data['registration_date'],
                    'diocese_id' => Diocese::where('code', 'MD')->firstOrFail()->id,
                    'diocesan_code' => 'SYS-'.Str::uuid(), 'ownership' => 'other', 'operational_status' => School::STATUS_ACTIVE,
                ]);
                $user = User::create(['school_id' => $school->id, 'name' => $data['headteacher_name'], 'email' => $data['email'], 'telephone' => $data['telephone'], 'password' => 'mdes123', 'designation' => 'School Headteacher', 'status' => User::STATUS_ACTIVE]);
                $user->roles()->attach($role);
                $school->update(['created_by' => $user->id, 'updated_by' => $user->id]);
                app(SchoolProfileContactService::class)->sync($school, ['headteacher_name' => $data['headteacher_name'], 'headteacher_phone' => $data['telephone'], 'headteacher_email' => $data['email']]);
                foreach (['first_deputy' => 'first_deputy', 'second_deputy' => 'second_deputy'] as $key => $position) {
                    $school->leadershipHistories()->create(['position' => $position, 'person_name' => $data[$key.'_name'], 'telephone' => $data[$key.'_phone'], 'effective_from' => $data['registration_date'], 'is_current' => true]);
                }
                $school->contact()->create(['telephone' => $data['telephone'], 'email' => $data['email']]);
                $school->statusHistories()->create(['previous_status' => null, 'new_status' => School::STATUS_ACTIVE, 'effective_date' => now()->toDateString(), 'reason' => 'School self-registration', 'responsible_user_id' => $user->id]);
                $request->setUserResolver(fn () => $user);
                $audit->handle($request, 'school.self_registered', $school, null, $school->toArray(), 'School profile registration');

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['official_name' => 'A school or account with these registration details already exists. Sign in or contact the Secretariat.']);
        }

        return redirect()->route('login')->with('registered_username', $user->school->ministry_registration_number)->with('status', 'School registered. Sign in using your MOE Reg No and default password mdes123. You can change your password later in Settings.');
    }
}
