<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'school_return_id',
    'board_active',
    'last_board_meeting_date',
    'board_meetings_held',
    'pta_active',
    'child_protection_policy_available',
    'financial_policy_available',
    'risk_register_available',
    'mandatory_reports_submitted',
    'open_compliance_issues',
    'leadership_notes',
    'compliance_notes',
])]
class GovernanceComplianceStatistic extends Model
{
    protected function casts(): array
    {
        return [
            'board_active' => 'boolean',
            'last_board_meeting_date' => 'date',
            'pta_active' => 'boolean',
            'child_protection_policy_available' => 'boolean',
            'financial_policy_available' => 'boolean',
            'risk_register_available' => 'boolean',
            'mandatory_reports_submitted' => 'boolean',
        ];
    }

    public function schoolReturn(): BelongsTo
    {
        return $this->belongsTo(SchoolReturn::class);
    }
}
