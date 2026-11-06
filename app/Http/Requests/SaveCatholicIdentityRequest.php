<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCatholicIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'has_chaplaincy' => ['nullable', 'boolean'],
            'pastoral_contact' => ['nullable', 'string', 'max:160'],
            'mass_frequency' => ['nullable', 'string', 'max:120'],
            'retreats_held' => ['required', 'integer', 'min:0'],
            'catechesis_available' => ['nullable', 'boolean'],
            'values_education_available' => ['nullable', 'boolean'],
            'safeguarding_structure_available' => ['nullable', 'boolean'],
            'counselling_structure_available' => ['nullable', 'boolean'],
            'parish_school_engagements' => ['required', 'integer', 'min:0'],
            'pastoral_programmes' => ['nullable', 'string', 'max:1500'],
            'notes' => ['nullable', 'string', 'max:1500'],
        ];
    }
}
