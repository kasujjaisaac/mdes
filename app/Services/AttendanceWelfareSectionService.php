<?php

namespace App\Services;

use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class AttendanceWelfareSectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(SchoolReturn $return, array $data): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }

        $expected = (int) ($data['expected_attendance'] ?? 0);
        $aggregate = (int) ($data['aggregate_attendance'] ?? 0);
        $enrolled = (int) ($data['enrolled_learners'] ?? 0);

        if ($aggregate > $expected) {
            throw new DomainException('aggregate attendance cannot exceed expected attendance.');
        }

        if ((int) ($data['retained_learners'] ?? 0) > $enrolled) {
            throw new DomainException('retained learners cannot exceed enrolled learners.');
        }

        $attendanceRate = $expected > 0 ? round(($aggregate / $expected) * 100, 2) : 0;

        $return->attendanceWelfareStatistic()->updateOrCreate(['school_return_id' => $return->id], [
            ...$data,
            'attendance_rate' => $attendanceRate,
        ]);

        $return->sections()->updateOrCreate(['section' => 'attendance_welfare'], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
