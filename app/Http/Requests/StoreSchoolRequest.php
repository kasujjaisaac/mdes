<?php

namespace App\Http\Requests;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schools.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ministry_registration_number' => is_string($this->input('ministry_registration_number')) ? School::normalizeMoeNumber($this->input('ministry_registration_number')) : $this->input('ministry_registration_number')]);
    }

    public function messages(): array
    {
        return ['ministry_registration_number.required' => 'Registration number is required.', 'ministry_registration_number.unique' => 'This registration number is already registered. Sign in to the existing account or contact the Secretariat.'];
    }

    public function rules(): array
    {
        return [
            'headteacher_name' => ['nullable', 'string', 'max:255'],
            'headteacher_phone' => ['nullable', 'string', 'max:40'],
            'headteacher_email' => ['nullable', 'email', 'max:255'],
            'contact' => ['sometimes', 'array:telephone,email,postal_address'],
            'contact.telephone' => ['nullable', 'string', 'max:40'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.postal_address' => ['nullable', 'string', 'max:1000'],
            'official_name' => ['required', 'string', 'max:255'],
            'parish_id' => ['required', 'integer', 'exists:parishes,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'school_level' => ['required', Rule::in(['nursery', 'primary', 'secondary', 'vocational_technical', 'tertiary'])],
            'ministry_registration_number' => ['required', 'string', 'max:80', Rule::unique('schools', 'ministry_registration_number')->ignore($this->route('school')), Rule::unique('users', 'username')->ignore($this->route('school') ? User::where('school_id', $this->route('school')->id)->value('id') : null)],
            'emis_number' => ['nullable', 'string', 'max:60', Rule::unique('schools', 'emis_number')->ignore($this->route('school'))],
            'funding_classification' => ['nullable', Rule::in(['Government', 'Private'])],
            'gender_classification' => ['nullable', Rule::in(['Boys', 'Girls', 'Mixed'])],
            'boarding_status' => ['nullable', Rule::in(['Day', 'Boarding', 'Both day and boarding'])],
        ];
    }
}
