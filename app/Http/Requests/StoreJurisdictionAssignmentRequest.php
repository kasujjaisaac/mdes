<?php

namespace App\Http\Requests;

use App\Models\JurisdictionAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJurisdictionAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('diocesan-structure.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id', 'required_without:education_cluster_id'],
            'education_cluster_id' => ['nullable', 'integer', 'exists:education_clusters,id'],
            'assignment_type' => ['required', Rule::in([JurisdictionAssignment::TYPE_PARISH_COORDINATOR, JurisdictionAssignment::TYPE_INSPECTOR])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
