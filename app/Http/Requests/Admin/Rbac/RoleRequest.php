<?php

namespace App\Http\Requests\Admin\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guard_name' => $this->input('guard_name') ?: 'web',
            'team_id' => $this->team_id !== '' ? $this->team_id : null,
        ]);
    }

    public function rules(): array
    {
        $roleId = $this->route('role')?->id ?? null;
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($roleId)],
            'guard_name' => ['required', 'in:web'],
            'team_id' => ['nullable', 'integer', 'exists:apps,id'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_mutable' => ['nullable', 'boolean'],
        ];
    }
}
