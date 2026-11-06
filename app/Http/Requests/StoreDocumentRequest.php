<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'document_category_id' => ['required', 'exists:document_categories,id'],
            'school_id' => ['nullable', 'exists:schools,id'],
            'title' => ['required', 'string', 'max:180'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'sensitivity' => ['required', 'in:public,internal,confidential,restricted'],
            'notes' => ['nullable', 'string', 'max:1500'],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ];
    }
}
