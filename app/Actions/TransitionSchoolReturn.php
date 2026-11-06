<?php

namespace App\Actions;

use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\ReturnQualityService;
use App\Services\StatisticalFormQualityService;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransitionSchoolReturn
{
    public function __construct(private readonly ReturnQualityService $qualityService) {}

    public function submit(SchoolReturn $return, User $user): SchoolReturn
    {
        return DB::transaction(function () use ($return, $user) {
            School::whereKey($return->school_id)->lockForUpdate()->firstOrFail();
            $return = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $return->load('reportingPeriod');
            $year = $return->reportingPeriod->year;
            if (SchoolReturn::where('school_id', $return->school_id)->where('id', '!=', $return->id)->whereNotNull('submitted_at')->whereHas('reportingPeriod', fn ($q) => $q->where('year', $year))->exists()) {
                throw new DomainException('Your school has already submitted its annual form for '.$year.'.');
            }
            if ($user->isSchoolRepresentative() && ($user->school_id !== $return->school_id || ! $return->profile_confirmed_at || ! $return->reportingPeriod->isSubmissionOpen())) {
                throw new DomainException('Confirm your school profile and submit during an open submission window.');
            }
            $this->assertStatus($return, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]);
            $this->assertSectionsComplete($return);
            $this->assertNoBlockingQualityFindings($return);

            return tap($return)->update([
                'status' => SchoolReturn::STATUS_SUBMITTED,
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'correction_reason' => null,
            ]);
        });
    }

    public function certify(SchoolReturn $return, User $user): SchoolReturn
    {
        $this->assertStatus($return, [SchoolReturn::STATUS_SUBMITTED]);

        $return = tap($return)->update([
            'status' => SchoolReturn::STATUS_CERTIFIED,
            'certified_by' => $user->id,
            'certified_at' => now(),
        ]);

        $return->certifications()->create([
            'certified_by' => $user->id,
            'declaration' => 'I certify that this return has been reviewed by the school and is accurate to the best of our knowledge.',
            'snapshot' => $return->fresh([
                'enrolmentStatistics',
                'attendanceWelfareStatistic',
                'staffStatistic',
                'academicPerformanceStatistics',
                'infrastructureStatistics',
                'catholicIdentityStatistic',
                'governanceComplianceStatistic',
            ])->toArray(),
            'certified_at' => now(),
        ]);

        return $return;
    }

    public function returnForCorrection(SchoolReturn $return, User $user, string $reason): SchoolReturn
    {
        $this->assertStatus($return, [SchoolReturn::STATUS_SUBMITTED, SchoolReturn::STATUS_CERTIFIED]);

        return tap($return)->update([
            'status' => SchoolReturn::STATUS_RETURNED_FOR_CORRECTION,
            'correction_reason' => $reason,
        ]);
    }

    public function approve(SchoolReturn $return, User $user): SchoolReturn
    {
        $this->assertStatus($return, [SchoolReturn::STATUS_CERTIFIED]);

        return tap($return)->update([
            'status' => SchoolReturn::STATUS_APPROVED,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);
    }

    public function lock(SchoolReturn $return, User $user): SchoolReturn
    {
        $this->assertStatus($return, [SchoolReturn::STATUS_APPROVED]);

        return tap($return)->update([
            'status' => SchoolReturn::STATUS_LOCKED,
            'locked_at' => now(),
        ]);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function assertStatus(SchoolReturn $return, array $allowed): void
    {
        if (! in_array($return->status, $allowed, true)) {
            throw new DomainException("invalid return transition from {$return->status}.");
        }
    }

    private function assertSectionsComplete(SchoolReturn $return): void
    {
        if ($return->statistical_form !== null) {
            $errors = app(StatisticalFormQualityService::class)->errors($return);
            if ($errors) {
                throw new DomainException(implode(' ', $errors));
            }

            return;
        }
        $missing = $return->sections()
            ->whereIn('section', $this->qualityService->requiredSections())
            ->where('status', '!=', 'complete')
            ->exists();

        if ($missing) {
            throw new DomainException('all required return sections must be complete before submission.');
        }
    }

    private function assertNoBlockingQualityFindings(SchoolReturn $return): void
    {
        $findings = $this->qualityService->validate($return);
        $hasErrors = collect($findings)->contains(fn (array $finding): bool => $finding['severity'] === 'error');

        if ($hasErrors) {
            throw new DomainException('blocking data quality errors must be resolved before submission.');
        }
    }
}
