<?php

namespace Database\Seeders;

use App\Models\{Role, School, SchoolReturn, User};
use App\Services\StatisticalFormSummaryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PrinceDemoSchoolSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo data is restricted to local/testing.');
        }

        DB::transaction(function (): void {
            $user = User::where('email', 'bennie42@example.com')->firstOrFail();
            $sample = SchoolReturn::whereHas('school', fn ($q) => $q->where('diocesan_code', 'like', 'DEMO-%')->where('school_level', 'primary'))
                ->whereHas('reportingPeriod', fn ($q) => $q->where('year', 2026)->where('status', 'open')->where('title', 'like', 'DEMO %'))
                ->whereNotNull('statistical_form')->with('school', 'reportingPeriod')->latest('id')->firstOrFail();
            $school = School::firstOrCreate(['diocesan_code' => 'DEMO-PRINCE'], [
                'official_name' => 'Demo St Prince Primary School',
                'diocese_id' => $sample->school->diocese_id,
                'parish_id' => $sample->school->parish_id,
                'district_id' => $sample->school->district_id,
                'school_level' => 'primary', 'ownership' => 'other',
                'funding_classification' => 'Private', 'gender_classification' => 'Mixed',
                'boarding_status' => 'Day', 'operational_status' => 'active',
                'emis_number' => 'DEMO-PRINCE-EMIS',
                'ministry_registration_number' => 'DEMO-PRINCE-REG',
            ]);
            if ($user->school_id && $user->school_id !== $school->id) {
                throw new \RuntimeException('User already has a different school assignment.');
            }
            $school->contact()->firstOrCreate([], ['email' => $user->email, 'postal_address' => 'Synthetic demo school, Masaka']);
            $user->update(['school_id' => $school->id]);
            $user->roles()->sync([Role::where('slug', 'school-representative')->firstOrFail()->id]);

            $period = $sample->reportingPeriod;
            $period->schools()->syncWithoutDetaching([$school->id]);
            $form = $sample->statistical_form;
            $form['identity'] = array_replace($form['identity'] ?? [], [
                'school_name' => $school->official_name,
                'registration_number' => $school->ministry_registration_number,
                'emis_code' => $school->emis_number,
                'headteacher_name' => 'Prince Wiegand',
                'funding' => 'Private', 'boarding' => 'Day',
            ]);
            $form['declaration'] = [];
            $record = SchoolReturn::firstOrCreate([
                'school_id' => $school->id, 'reporting_period_id' => $period->id,
            ], ['status' => 'draft', 'started_by' => $user->id, 'statistical_form' => $form]);
            if ($record->wasRecentlyCreated) {
                foreach (['enrolment', 'teaching'] as $section) {
                    app(StatisticalFormSummaryService::class)->sync($record, $section, $form[$section]);
                }
            }
            $this->command?->info('Assigned '.$school->official_name.' to '.$user->email.'; sample return #'.$record->id.' ('.$record->status.').');
        });
    }
}
