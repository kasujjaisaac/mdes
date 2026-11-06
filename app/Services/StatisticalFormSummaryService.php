<?php
namespace App\Services;

use App\Models\SchoolReturn;

class StatisticalFormSummaryService
{
    public function sync(SchoolReturn $return, string $section, array $data): void
    {
        if ($section === 'enrolment') {
            $return->enrolmentStatistics()->delete();
            foreach (['classes', 'nursery'] as $group) foreach ($data[$group] ?? [] as $key => $row) {
                $boys = (int) ($row['boys'] ?? 0);
                $girls = (int) ($row['girls'] ?? 0);
                $return->enrolmentStatistics()->create([
                    'class_level' => $group === 'nursery' ? 'nursery_'.$key : $key,
                    'boys' => $boys, 'girls' => $girls, 'total' => $boys + $girls,
                    'notes' => 'Statistical Form 2026',
                ]);
            }
        }
        if (in_array($section, ['teaching', 'support'])) {
            $form = $return->statistical_form;
            $teaching = collect($form['teaching']['members'] ?? []);
            $support = collect($form['support']['members'] ?? []);
            $all = $teaching->concat($support);
            $return->staffStatistic()->updateOrCreate(['school_return_id' => $return->id], [
                'male_teaching_staff' => $teaching->where('gender', 'Male')->count(),
                'female_teaching_staff' => $teaching->where('gender', 'Female')->count(),
                'male_non_teaching_staff' => $support->where('gender', 'Male')->count(),
                'female_non_teaching_staff' => $support->where('gender', 'Female')->count(),
                'government_payroll_staff' => $all->where('payroll', 'Government')->count(),
                'private_payroll_staff' => $all->where('payroll', 'Private')->count(),
                'filled_positions' => $all->count(),
            ]);
        }
    }
}
