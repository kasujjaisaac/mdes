<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use App\Models\ExaminationType;
use Illuminate\Database\Seeder;

class PhaseNineToFourteenSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['PLE', 'UCE', 'UACE', 'UBTEB', 'DIT', 'Mock', 'Internal examinations'] as $name) {
            $exam = ExaminationType::query()->updateOrCreate([
                'slug' => str($name)->slug()->toString(),
            ], [
                'name' => $name,
                'is_national' => in_array($name, ['PLE', 'UCE', 'UACE', 'UBTEB', 'DIT'], true),
                'is_active' => true,
            ]);

            foreach (['division 1', 'division 2', 'division 3', 'division 4', 'ungraded'] as $index => $grade) {
                $exam->gradingScales()->updateOrCreate(['grade' => $grade], [
                    'sort_order' => $index + 1,
                    'is_passing' => $grade !== 'ungraded',
                ]);
            }
        }

        foreach (['certificates', 'licences', 'policies', 'reports', 'land records', 'meeting minutes', 'inspection evidence', 'project evidence', 'other'] as $name) {
            DocumentCategory::query()->updateOrCreate([
                'slug' => str($name)->slug()->toString(),
            ], [
                'name' => $name,
                'is_active' => true,
            ]);
        }
    }
}
