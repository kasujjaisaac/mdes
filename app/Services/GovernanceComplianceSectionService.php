<?php

namespace App\Services;

use App\Models\ReturnSection;
use App\Models\SchoolReturn;
use DomainException;

class GovernanceComplianceSectionService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function save(SchoolReturn $return, array $data): void
    {
        if ($return->isLocked()) {
            throw new DomainException('locked returns cannot be edited.');
        }

        $return->governanceComplianceStatistic()->updateOrCreate(['school_return_id' => $return->id], [
            ...$data,
            'board_active' => (bool) ($data['board_active'] ?? false),
            'pta_active' => (bool) ($data['pta_active'] ?? false),
            'child_protection_policy_available' => (bool) ($data['child_protection_policy_available'] ?? false),
            'financial_policy_available' => (bool) ($data['financial_policy_available'] ?? false),
            'risk_register_available' => (bool) ($data['risk_register_available'] ?? false),
            'mandatory_reports_submitted' => (bool) ($data['mandatory_reports_submitted'] ?? false),
        ]);

        $return->sections()->updateOrCreate(['section' => 'governance_compliance'], [
            'status' => ReturnSection::STATUS_COMPLETE,
            'validation_errors' => null,
            'completed_at' => now(),
        ]);
    }
}
