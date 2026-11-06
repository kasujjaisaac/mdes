<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('schools.manage') ?? false; }
    public function rules(): array
    {
        return [
            'official_name'=>['required','string','max:255'],
            'parish_id'=>['required','integer','exists:parishes,id'],
            'district_id'=>['nullable','integer','exists:districts,id'],
            'school_level'=>['required',Rule::in(['nursery','primary','secondary','vocational_technical','tertiary'])],
            'ministry_registration_number'=>['nullable','string','max:80',Rule::unique('schools','ministry_registration_number')->ignore($this->route('school'))],
            'emis_number'=>['nullable','string','max:60',Rule::unique('schools','emis_number')->ignore($this->route('school'))],
            'funding_classification'=>['nullable',Rule::in(['Government','Private'])],
            'gender_classification'=>['nullable',Rule::in(['Boys','Girls','Mixed'])],
            'boarding_status'=>['nullable',Rule::in(['Day','Boarding','Both day and boarding'])],
        ];
    }
}
