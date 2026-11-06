<?php

namespace App\Services;

use App\Models\DataQualityFinding;
use App\Models\SchoolReturn;

class ReturnQualityService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function validate(SchoolReturn $return): array
    {
        $return->qualityFindings()->delete();
        $findings = [];
        if ($return->statistical_form !== null) {
            foreach (app(StatisticalFormQualityService::class)->errors($return) as $rule => $message) {
                $findings[] = $this->finding($return, 'statistical_form_2026', DataQualityFinding::ERROR, $rule, $message);
            }
            return $findings;
        }

        $requiredSections = $this->requiredSections();
        $completed = $return->sections()->where('status', 'complete')->pluck('section')->all();

        foreach (array_diff($requiredSections, $completed) as $section) {
            $findings[] = $this->finding($return, $section, DataQualityFinding::ERROR, 'required_section', "{$section} must be completed before submission.");
        }

        if (($return->attendanceWelfareStatistic?->enrolled_learners ?? 0) !== 0) {
            $enrolment = $return->enrolmentStatistics()->sum('total');
            $attendanceEnrolment = $return->attendanceWelfareStatistic?->enrolled_learners ?? 0;

            if ($enrolment !== $attendanceEnrolment) {
                $findings[] = $this->finding($return, 'attendance_welfare', DataQualityFinding::WARNING, 'enrolment_reconciliation', 'attendance enrolled learners differs from enrolment section total.', [
                    'enrolment_total' => $enrolment,
                    'attendance_enrolled' => $attendanceEnrolment,
                ]);
            }
        }

        if ($return->infrastructureStatistics()->where('high_risk_condition', true)->exists()) {
            $findings[] = $this->finding($return, 'infrastructure', DataQualityFinding::WARNING, 'high_risk_infrastructure', 'one or more high-risk infrastructure conditions require review.');
        }

        return $findings;
    }

    /**
     * @return array<int, string>
     */
    public function requiredSections(): array
    {
        return [
            'enrolment',
            'attendance_welfare',
            'staff',
            'academic_performance',
            'infrastructure',
            'catholic_identity',
            'governance_compliance',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    private function finding(SchoolReturn $return, ?string $section, string $severity, string $rule, string $message, ?array $context = null): array
    {
        return $return->qualityFindings()->create(compact('section', 'severity', 'rule', 'message', 'context'))->toArray();
    }
}
