<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveEnrolmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.class_level' => ['required', 'string', 'max:80'],
            'rows.*.boys' => ['required', 'integer', 'min:0'],
            'rows.*.girls' => ['required', 'integer', 'min:0'],
            'rows.*.day_scholars' => ['nullable', 'integer', 'min:0'],
            'rows.*.boarders' => ['nullable', 'integer', 'min:0'],
            'rows.*.repeaters' => ['nullable', 'integer', 'min:0'],
            'rows.*.transfers_in' => ['nullable', 'integer', 'min:0'],
            'rows.*.transfers_out' => ['nullable', 'integer', 'min:0'],
            'rows.*.dropouts' => ['nullable', 'integer', 'min:0'],
            'rows.*.re_entries' => ['nullable', 'integer', 'min:0'],
            'rows.*.completions' => ['nullable', 'integer', 'min:0'],
            'rows.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
