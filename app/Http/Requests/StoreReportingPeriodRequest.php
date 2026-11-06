<?php

namespace App\Http\Requests;

use App\Models\ReportingPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reporting-periods.manage') ?? false;
    }

    public function messages(): array
    {
        return ['year.unique' => 'An annual submission window already exists for this year.'];
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['annual_census'])],
            'year' => ['required', 'integer', 'between:2020,2100', Rule::unique('reporting_periods', 'annual_year')],
            'term' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string'],
            'opening_date' => ['required', 'date'],
            'closing_date' => ['required', 'date', 'after_or_equal:opening_date'],
            'extension_date' => ['nullable', 'date', 'after_or_equal:closing_date'],
            'lock_date' => ['nullable', 'date', 'after_or_equal:closing_date'],
            'status' => ['required', Rule::in([ReportingPeriod::STATUS_DRAFT, ReportingPeriod::STATUS_OPEN, ReportingPeriod::STATUS_CLOSED, ReportingPeriod::STATUS_LOCKED])],
            'school_ids' => ['array'],
            'school_ids.*' => ['integer', 'exists:schools,id'],
        ];
    }
}
