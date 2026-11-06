<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveAttendanceWelfareRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enrolled_learners' => ['required', 'integer', 'min:0'],
            'expected_attendance' => ['required', 'integer', 'min:0'],
            'aggregate_attendance' => ['required', 'integer', 'min:0'],
            'retained_learners' => ['required', 'integer', 'min:0'],
            'dropouts' => ['required', 'integer', 'min:0'],
            're_entries' => ['required', 'integer', 'min:0'],
            'transitioned_learners' => ['required', 'integer', 'min:0'],
            'welfare_cases_aggregated' => ['required', 'integer', 'min:0'],
            'trend_explanation' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
