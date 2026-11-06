<?php

namespace App\Services;

use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class StaffStatisticsSectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(SchoolReturn $return, array $data): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }

        $teaching = (int) ($data['male_teaching_staff'] ?? 0) + (int) ($data['female_teaching_staff'] ?? 0);
        $qualified = (int) ($data['qualified_teachers'] ?? 0);
        $unqualified = (int) ($data['unqualified_teachers'] ?? 0);

        if ($qualified + $unqualified !== $teaching) {
            throw new DomainException('qualified plus unqualified teachers must equal total teaching staff.');
        }

        $establishment = (int) ($data['approved_establishment'] ?? 0);
        $filled = (int) ($data['filled_positions'] ?? 0);
        $vacancies = max(0, $establishment - $filled);
        $vacancyRate = $establishment > 0 ? round(($vacancies / $establishment) * 100, 2) : 0;
        $enrolment = $return->enrolmentStatistics()->sum('total');

        $return->staffStatistic()->updateOrCreate(['school_return_id' => $return->id], [
            ...$data,
            'vacancies' => $vacancies,
            'vacancy_rate' => $vacancyRate,
            'pupil_teacher_ratio' => $teaching > 0 ? round($enrolment / $teaching, 2) : null,
        ]);

        $return->sections()->updateOrCreate(['section' => 'staff'], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
