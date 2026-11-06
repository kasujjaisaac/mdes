<?php

namespace Database\Seeders;

use App\Models\InspectionTemplate;
use Illuminate\Database\Seeder;

class PhaseFifteenToTwentySixSeeder extends Seeder
{
    public function run(): void
    {
        InspectionTemplate::query()->updateOrCreate([
            'name' => 'standard school monitoring checklist',
            'version' => 1,
        ], [
            'checklist_items' => [
                'school leadership and governance',
                'learner attendance and welfare',
                'staffing and teaching quality',
                'infrastructure and safety',
                'Catholic identity and pastoral care',
            ],
            'is_active' => true,
        ]);
    }
}
