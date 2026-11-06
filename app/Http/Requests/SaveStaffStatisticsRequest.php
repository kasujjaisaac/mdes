<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveStaffStatisticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'male_teaching_staff' => ['required', 'integer', 'min:0'],
            'female_teaching_staff' => ['required', 'integer', 'min:0'],
            'male_non_teaching_staff' => ['required', 'integer', 'min:0'],
            'female_non_teaching_staff' => ['required', 'integer', 'min:0'],
            'qualified_teachers' => ['required', 'integer', 'min:0'],
            'unqualified_teachers' => ['required', 'integer', 'min:0'],
            'government_payroll_staff' => ['required', 'integer', 'min:0'],
            'private_payroll_staff' => ['required', 'integer', 'min:0'],
            'approved_establishment' => ['required', 'integer', 'min:0'],
            'filled_positions' => ['required', 'integer', 'min:0'],
            'training_needs' => ['nullable', 'string', 'max:1500'],
            'housing_gaps' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
