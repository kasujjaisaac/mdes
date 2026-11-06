<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_id' => [Rule::requiredIf(fn()=>\App\Models\Role::whereIn('id',(array)$this->input('role_ids',[]))->whereIn('slug',['school-representative','headteacher','school-data-officer'])->exists()), 'nullable','integer','exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'designation' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED, User::STATUS_DEACTIVATED])],
            'password' => ['required', 'string', 'min:8'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
