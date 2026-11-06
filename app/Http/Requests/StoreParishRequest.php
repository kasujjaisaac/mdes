<?php

namespace App\Http\Requests;

use App\Models\Diocese;
use Illuminate\Foundation\Http\FormRequest;

class StoreParishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('diocesan-structure.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['diocese_id' => Diocese::where('code', 'MD')->firstOrFail()->getKey()]);
    }

    public function rules(): array
    {
        return [
            'diocese_id' => ['required', 'integer', 'exists:dioceses,id'],
            'name' => ['required', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }
}
