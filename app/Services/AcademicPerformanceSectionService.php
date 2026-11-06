<?php

namespace App\Services;

use App\Models\ExaminationType;
use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class AcademicPerformanceSectionService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function save(SchoolReturn $return, array $rows): void
    {
        $this->assertEditable($return);
        $return->academicPerformanceStatistics()->delete();

        foreach ($rows as $row) {
            $registered = (int) ($row['registered_candidates'] ?? 0);
            $sat = (int) ($row['candidates_sat'] ?? 0);
            $absentees = (int) ($row['absentees'] ?? 0);
            $male = (int) ($row['male_candidates'] ?? 0);
            $female = (int) ($row['female_candidates'] ?? 0);
            $grades = $this->normaliseGrades($row['grade_distribution'] ?? []);
            $gradedTotal = array_sum($grades);

            if ($sat + $absentees !== $registered) {
                throw new DomainException('candidates who sat plus absentees must equal registered candidates.');
            }

            if ($male + $female !== $registered) {
                throw new DomainException('male plus female candidates must equal registered candidates.');
            }

            if ($gradedTotal > $sat) {
                throw new DomainException('grade totals cannot exceed candidates who sat.');
            }

            $passingGrades = ExaminationType::query()
                ->findOrFail((int) $row['examination_type_id'])
                ->gradingScales()
                ->where('is_passing', true)
                ->pluck('grade')
                ->all();
            $passed = collect($grades)->only($passingGrades)->sum();

            $return->academicPerformanceStatistics()->create([
                ...$row,
                'registered_candidates' => $registered,
                'candidates_sat' => $sat,
                'absentees' => $absentees,
                'male_candidates' => $male,
                'female_candidates' => $female,
                'grade_distribution' => $grades,
                'pass_rate' => $sat > 0 ? round(($passed / $sat) * 100, 2) : 0,
            ]);
        }

        $this->markComplete($return, 'academic_performance');
    }

    /**
     * @param  array<string, mixed>  $grades
     * @return array<string, int>
     */
    private function normaliseGrades(array $grades): array
    {
        return collect($grades)
            ->map(fn (mixed $count): int => max(0, (int) $count))
            ->filter(fn (int $count): bool => $count > 0)
            ->all();
    }

    private function assertEditable(SchoolReturn $return): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }
    }

    private function markComplete(SchoolReturn $return, string $section): void
    {
        $return->sections()->updateOrCreate(['section' => $section], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
