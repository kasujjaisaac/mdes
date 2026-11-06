<?php

namespace App\Actions;

use App\Models\ReportingPeriod;
use App\Models\ReturnSection;
use App\Models\School;
use App\Models\SchoolReturn;
use App\Models\User;
use App\Services\ReturnQualityService;
use DomainException;

class StartSchoolReturn
{
    public function __construct(private readonly ReturnQualityService $qualityService) {}

    public function handle(ReportingPeriod $period, School $school, User $user): SchoolReturn
    {
        if ($period->is_archived) throw new DomainException('This duplicate submission window has been archived.');
        if ($user->isSchoolRepresentative() && ($user->school_id !== $school->id || !$period->allowsRepresentativeDraft())) throw new DomainException('This school or collection window is not available to your account.');
        if (! $period->schools()->whereKey($school->id)->exists()) {
            throw new DomainException('school is not participating in this reporting period.');
        }

        $return = SchoolReturn::query()->firstOrCreate([
            'reporting_period_id' => $period->id,
            'school_id' => $school->id,
        ], [
            'status' => SchoolReturn::STATUS_DRAFT,
            'started_by' => $user->id,
        ]);

        foreach ($this->qualityService->requiredSections() as $section) {
            $return->sections()->firstOrCreate(['section' => $section], ['status' => ReturnSection::STATUS_NOT_STARTED]);
        }

        return $return;
    }
}
