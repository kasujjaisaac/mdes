<?php

namespace App\Services;

use App\Models\SchoolReturn;
use App\Models\SubmissionVersion;
use App\Models\User;
use App\Support\StatisticalForm;

class SubmissionVersionService
{
    // Called while the parent return is locked by the submission/review transaction.
    public function capture(SchoolReturn $return, bool $legacy = false): SubmissionVersion
    {
        $return->loadMissing('school.parish', 'school.district', 'reportingPeriod');
        $school = $return->school->only(['id', 'official_name', 'ministry_registration_number', 'emis_number', 'parish_id', 'district_id', 'school_level', 'funding_classification', 'gender_classification', 'boarding_status']);
        $school['parish_name'] = $return->school->parish?->name;
        $school['district_name'] = $return->school->district?->name;
        $payload = ['form' => $return->statistical_form ?? [], 'schema' => StatisticalForm::sectionsForReturn($return), 'introduction' => json_decode(file_get_contents(resource_path('forms/statistical-2026-introduction.json')), true, 512, JSON_THROW_ON_ERROR), 'profile' => $return->profile_snapshot ?? [], 'school' => $school,
            'period' => $return->reportingPeriod->only(['id', 'title', 'year']), 'submitter' => User::find($return->submitted_by)?->only(['id', 'name'])];

        return SubmissionVersion::create(['school_return_id' => $return->id, 'version' => (int) SubmissionVersion::where('school_return_id', $return->id)->max('version') + 1,
            'payload' => $payload, 'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)), 'review_status' => $return->status,
            'submitted_by' => $return->submitted_by, 'submitted_at' => $return->submitted_at, 'legacy_import' => $legacy]);
    }

    public function review(SchoolReturn $return, string $status, User $user, ?string $reason = null): void
    {
        $version = SubmissionVersion::where('school_return_id', $return->id)->latest('version')->first() ?? $this->capture($return, true);
        $version->update(['review_status' => $status, 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'correction_reason' => $reason]);
    }

    public function forDisplay(SchoolReturn $return): SchoolReturn
    {
        $version = SubmissionVersion::where('school_return_id', $return->id)->latest('version')->first();
        $copy = clone $return;
        $copy->statistical_form_drafts = [];
        $copy->setRelation('displayVersion', $version);
        if ($version) {
            $copy->statistical_form = $version->payload['form'] ?? [];
            $copy->profile_snapshot = $version->payload['profile'] ?? [];
            $school = clone $return->school;
            $copy->setRelation('school', $school->forceFill($version->payload['school']));
            $period = clone $return->reportingPeriod;
            $copy->setRelation('reportingPeriod', $period->forceFill($version->payload['period']));
        } elseif ($return->status === SchoolReturn::STATUS_RETURNED_FOR_CORRECTION) {
            // Never expose a school's unsent corrections as its earlier submission.
            $copy->statistical_form = [];
        }

        return $copy;
    }
}
