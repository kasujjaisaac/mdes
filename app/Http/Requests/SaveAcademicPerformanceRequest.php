<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAcademicPerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.examination_type_id' => ['required', 'exists:examination_types,id'],
            'rows.*.registered_candidates' => ['required', 'integer', 'min:0'],
            'rows.*.candidates_sat' => ['required', 'integer', 'min:0'],
            'rows.*.absentees' => ['required', 'integer', 'min:0'],
            'rows.*.male_candidates' => ['required', 'integer', 'min:0'],
            'rows.*.female_candidates' => ['required', 'integer', 'min:0'],
            'rows.*.grade_distribution' => ['nullable', 'array'],
            'rows.*.grade_distribution.*' => ['nullable', 'integer', 'min:0'],
            'rows.*.weaknesses' => ['nullable', 'string', 'max:1500'],
            'rows.*.intervention_notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
