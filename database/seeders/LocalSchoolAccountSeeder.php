<?php

namespace Database\Seeders;

use App\Models\Diocese;
use App\Models\District;
use App\Models\Parish;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\SchoolProfileContactService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalSchoolAccountSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo school accounts are restricted to local/testing environments.');
        }

        DB::transaction(function (): void {
            $existing = User::where('email', 'headteacher.demo@mdsiss.test')->first();
            if ($existing) {
                $this->command?->info('Demo account already exists; credentials and assignment preserved.');

                return;
            }

            $diocese = Diocese::where('code', 'MD')->firstOrFail();
            $parish = Parish::where('diocese_id', $diocese->id)->where('is_active', true)->orderBy('id')->firstOrFail();
            $district = District::where('is_active', true)->orderBy('id')->firstOrFail();
            $role = Role::where('slug', Role::HEADTEACHER)->firstOrFail();
            $date = now('Africa/Kampala')->toDateString();
            $school = School::create([
                'official_name' => 'Demo School Account',
                'diocesan_code' => 'DEMO-LOGIN-01',
                'ministry_registration_number' => 'DEMO-MOE-2026',
                'emis_number' => 'DEMO-EMIS-2026',
                'registration_date' => $date,
                'diocese_id' => $diocese->id,
                'parish_id' => $parish->id,
                'district_id' => $district->id,
                'school_level' => 'primary',
                'ownership' => 'other',
                'funding_classification' => 'Private',
                'gender_classification' => 'Mixed',
                'boarding_status' => 'Day',
                'operational_status' => School::STATUS_ACTIVE,
            ]);
            $user = User::create([
                'school_id' => $school->id,
                'name' => 'Demo Headteacher',
                'first_name' => 'Demo',
                'last_name' => 'Headteacher',
                'email' => 'headteacher.demo@mdsiss.test',
                'password' => 'mdes123',
                'designation' => 'School Headteacher',
                'status' => User::STATUS_ACTIVE,
            ]);
            $user->roles()->attach($role);
            $school->update(['created_by' => $user->id, 'updated_by' => $user->id]);
            app(SchoolProfileContactService::class)->sync($school, ['headteacher_name' => $user->name, 'headteacher_email' => $user->email]);
            foreach (['first_deputy', 'second_deputy'] as $position) {
                $school->leadershipHistories()->create([
                    'position' => $position,
                    'person_name' => 'Demo '.str_replace('_', ' ', $position),
                    'effective_from' => $date,
                    'is_current' => true,
                ]);
            }
            $school->contact()->create(['email' => $user->email]);
            $this->command?->info('Demo school Headteacher account created. Username: '.$user->username);
        });
    }
}
