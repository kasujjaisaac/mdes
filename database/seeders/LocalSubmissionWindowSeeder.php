<?php

namespace Database\Seeders;

use App\Models\ReportingPeriod;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalSubmissionWindowSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo submission windows are restricted to local/testing environments.');
        }

        DB::transaction(function (): void {
            $school = School::where('ministry_registration_number', 'DEMO-MOE-2026')->firstOrFail();
            $admin = User::whereHas('roles', fn ($query) => $query->where('slug', Role::SUPER_ADMINISTRATOR))->where('status', User::STATUS_ACTIVE)->firstOrFail();
            $today = now('Africa/Kampala');
            // Reuse the one annual window permitted for the reporting year.
            $period = ReportingPeriod::firstOrCreate(
                ['annual_year' => $today->year],
                ['title' => 'DEMO '.$today->year.' Annual Collection', 'type' => 'annual_census', 'year' => $today->year, 'created_by' => $admin->id,
                    'opening_date' => $today->toDateString(), 'closing_date' => $today->copy()->addDays(30)->toDateString(), 'status' => ReportingPeriod::STATUS_OPEN,
                    'instructions' => 'Local testing window. Complete your school profile, then test the statistical form. Synthetic data only.'],
            );
            $period->update([
                'status' => ReportingPeriod::STATUS_OPEN,
                'opening_date' => $today->toDateString(),
                'closing_date' => $today->copy()->addDays(30)->toDateString(),
                'extension_date' => null,
                'lock_date' => null,
            ]);
            $period->schools()->syncWithoutDetaching([$school->id]);
            $this->command?->info('Open window: '.$period->title.' | Closes: '.$period->closing_date->format('j F Y').' | Assigned: '.$school->official_name);
        });
    }
}
