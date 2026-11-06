<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveInfrastructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.facility_type' => ['required', 'string', 'max:80'],
            'rows.*.total' => ['required', 'integer', 'min:0'],
            'rows.*.functional' => ['required', 'integer', 'min:0'],
            'rows.*.damaged' => ['required', 'integer', 'min:0'],
            'rows.*.required' => ['required', 'integer', 'min:0'],
            'rows.*.condition' => ['required', 'in:good,fair,poor,critical'],
            'rows.*.priority' => ['required', 'in:low,normal,high,urgent'],
            'rows.*.water_source' => ['nullable', 'string', 'max:120'],
            'rows.*.water_reliability' => ['nullable', 'string', 'max:120'],
            'rows.*.has_electricity' => ['nullable', 'boolean'],
            'rows.*.has_internet' => ['nullable', 'boolean'],
            'rows.*.computers' => ['nullable', 'integer', 'min:0'],
            'rows.*.fire_safety_available' => ['nullable', 'boolean'],
            'rows.*.accessibility_available' => ['nullable', 'boolean'],
            'rows.*.security_fenced' => ['nullable', 'boolean'],
            'rows.*.high_risk_condition' => ['nullable', 'boolean'],
            'rows.*.notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
