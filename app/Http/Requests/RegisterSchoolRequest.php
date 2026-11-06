<?php

namespace App\Http\Requests;

use App\Models\Diocese;
use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['ministry_registration_number' => is_string($this->input('ministry_registration_number')) ? School::normalizeMoeNumber($this->input('ministry_registration_number')) : $this->input('ministry_registration_number'), 'email' => is_string($this->input('email')) ? strtolower(trim($this->input('email'))) : $this->input('email'), 'official_name' => is_string($this->input('official_name')) ? trim($this->input('official_name')) : $this->input('official_name')]);
    }

    public function messages(): array
    {
        return ['ministry_registration_number.required' => 'MOE Reg No is required.', 'ministry_registration_number.unique' => 'This MOE Reg No is already registered. Sign in to the existing account or contact the Secretariat.'];
    }

    public function rules(): array
    {
        return [
            'official_name' => ['required', 'string', 'max:255', Rule::unique('schools', 'official_name')->where('parish_id', $this->input('parish_id'))],
            'parish_id' => ['required', 'integer', Rule::exists('parishes', 'id')->where('is_active', true)->where(fn ($q) => $q->whereIn('diocese_id', Diocese::where('code', 'MD')->select('id')))],
            'district_id' => ['required', 'integer', Rule::exists('districts', 'id')->where('is_active', true)],
            'ministry_registration_number' => ['required', 'string', 'max:80', 'not_regex:/@/', 'unique:schools,ministry_registration_number', 'unique:users,username'],
            'emis_number' => ['required', 'string', 'max:60', 'unique:schools,emis_number'],
            'school_level' => ['required', Rule::in(['nursery', 'primary', 'secondary', 'vocational_technical', 'tertiary'])],
            'funding_classification' => ['required', Rule::in(['Government', 'Private'])],
            'gender_classification' => ['required', Rule::in(['Boys', 'Girls', 'Mixed'])],
            'boarding_status' => ['required', Rule::in(['Day', 'Boarding', 'Both day and boarding'])],
            'headteacher_name' => ['required', 'string', 'max:255'],
            'first_deputy_name' => ['required', 'string', 'min:2', 'max:255'],
            'second_deputy_name' => ['required', 'string', 'min:2', 'max:255'],
            'first_deputy_phone' => ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){7,15}\D*$)\+?[0-9][0-9 ()-]{6,24}$/'],
            'second_deputy_phone' => ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){7,15}\D*$)\+?[0-9][0-9 ()-]{6,24}$/'],
            'telephone' => ['required', 'string', 'max:40', 'regex:/^(?=(?:\D*\d){7,15}\D*$)\+?[0-9][0-9 ()-]{6,24}$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ];
    }
}
