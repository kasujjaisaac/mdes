<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveGovernanceComplianceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'board_active' => ['nullable', 'boolean'],
            'last_board_meeting_date' => ['nullable', 'date'],
            'board_meetings_held' => ['required', 'integer', 'min:0'],
            'pta_active' => ['nullable', 'boolean'],
            'child_protection_policy_available' => ['nullable', 'boolean'],
            'financial_policy_available' => ['nullable', 'boolean'],
            'risk_register_available' => ['nullable', 'boolean'],
            'mandatory_reports_submitted' => ['nullable', 'boolean'],
            'open_compliance_issues' => ['required', 'integer', 'min:0'],
            'leadership_notes' => ['nullable', 'string', 'max:1500'],
            'compliance_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
