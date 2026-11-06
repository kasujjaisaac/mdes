<?php

namespace App\Http\Requests;

use App\Models\Role;
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
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function messages(): array
    {
        return ['school_id.unique' => 'This school already has a user account. Edit or recover that account instead of creating another.'];
    }

    public function rules(): array
    {
        return [
            'school_id' => [Rule::prohibitedIf(fn () => Role::whereIn('id', array_filter((array) $this->input('role_ids', []), fn ($id) => is_scalar($id) && ctype_digit((string) $id)))->where('slug', Role::SUPER_ADMINISTRATOR)->exists()), Rule::requiredIf(fn () => Role::whereIn('id', array_filter((array) $this->input('role_ids', []), fn ($id) => is_scalar($id) && ctype_digit((string) $id)))->whereIn('slug', [Role::HEADTEACHER])->exists()), 'nullable', 'integer', 'exists:schools,id', Rule::unique('users', 'school_id')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED, User::STATUS_DEACTIVATED])],
            'password' => ['required', 'string', 'min:12', 'max:4096', 'confirmed'],
            'role_ids' => ['required', 'array', 'min:1', 'max:1'],
            'role_ids.*' => ['integer', 'distinct', Rule::exists('roles', 'id')->whereIn('slug', Role::ACCOUNT_ROLES)],
        ];
    }
}
