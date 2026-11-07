<?php

namespace App\Actions;

use App\Models\ReportingPeriod;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\ReturnQualityService;
use App\Services\StatisticalFormQualityService;
use App\Services\SubmissionVersionService;
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
            $return->setRelation('reportingPeriod', ReportingPeriod::whereKey($return->reporting_period_id)->lockForUpdate()->firstOrFail());
            $year = $return->reportingPeriod->year;
            if (SchoolReturn::where('school_id', $return->school_id)->where('id', '!=', $return->id)->whereNotNull('submitted_at')->whereHas('reportingPeriod', fn ($q) => $q->where('year', $year))->exists()) {
                throw new DomainException('Your school has already submitted its annual form for '.$year.'.');
            }
            if ($user->isSchoolRepresentative() && ($user->school_id !== $return->school_id || ! $return->profile_confirmed_at || ($return->reportingPeriod->is_archived || ($return->status !== SchoolReturn::STATUS_RETURNED_FOR_CORRECTION && ! $return->reportingPeriod->isSubmissionOpen())))) {
                throw new DomainException('Confirm your school profile and submit during an open submission window.');
            }
            if ($user->isSchoolRepresentative() && ! $return->reportingPeriod->schools()->where('schools.id', $return->school_id)->exists()) {
                throw new DomainException('Your school is not assigned to this submission window. Ask the administrator to add your school before submitting.');
            }
            $this->assertStatus($return, [SchoolReturn::STATUS_DRAFT, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION]);
            $this->assertSectionsComplete($return);
            $this->assertNoBlockingQualityFindings($return);

            $return->update([
                'status' => SchoolReturn::STATUS_LOCKED,
                'locked_at' => now(),
                'submitted_by' => $user->id,
                'submitted_at' => now(),
                'statistical_form' => $return->statistical_form === null ? null : [
                    ...$return->statistical_form,
                    'declaration' => ['acknowledged' => true, 'acknowledged_by' => $user->id, 'acknowledged_at' => now()->toIso8601String()],
                ],
                'correction_reason' => null,
                'approved_by' => null,
                'approved_at' => null,
            ]);
            app(SubmissionVersionService::class)->capture($return->fresh());

            return $return;
        });
    }

    public function approve(SchoolReturn $return, User $user): SchoolReturn
    {
        return DB::transaction(function () use ($return, $user) {
            $return = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($return, SchoolReturn::PENDING_REVIEW_STATUSES);
            if (! $return->submitted_at || $return->reportingPeriod->is_archived) {
                throw new DomainException('Only submitted forms in an unarchived reporting window can be approved.');
            }

            app(SubmissionVersionService::class)->review($return, SchoolReturn::STATUS_APPROVED, $user);

            return tap($return)->update(['status' => SchoolReturn::STATUS_APPROVED, 'approved_by' => $user->id, 'approved_at' => now()]);
        });
    }

    public function returnForCorrection(SchoolReturn $return, User $user, string $reason): SchoolReturn
    {
        return DB::transaction(function () use ($return, $reason, $user) {
            $return = SchoolReturn::whereKey($return->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($return, SchoolReturn::PENDING_REVIEW_STATUSES);
            if (blank($reason)) {
                throw new DomainException('Explain the corrections the school needs to make.');
            }
            if (! $return->submitted_at || $return->reportingPeriod->is_archived) {
                throw new DomainException('Archived returns cannot be sent for correction.');
            }

            app(SubmissionVersionService::class)->review($return, SchoolReturn::STATUS_RETURNED_FOR_CORRECTION, $user, $reason);

            return tap($return)->update([
                'status' => SchoolReturn::STATUS_RETURNED_FOR_CORRECTION,
                'locked_at' => null,
                'correction_reason' => $reason,
            ]);
        });
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
        if ($return->statistical_form !== null || ! empty($return->statistical_form_drafts)) {
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
