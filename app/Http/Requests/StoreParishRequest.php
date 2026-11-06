<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreParishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('diocesan-structure.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'diocese_id' => ['required', 'integer', 'exists:dioceses,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:30', 'unique:parishes,code'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
