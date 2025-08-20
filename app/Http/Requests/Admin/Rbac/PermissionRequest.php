<?php

namespace App\Http\Requests\Admin\Rbac;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Default guard_name to web; default key to name if missing
        $name = $this->input('name');
        $key = $this->input('key');
        $this->merge([
            'guard_name' => $this->input('guard_name') ?: 'web',
            'key' => $key ?: $name,
        ]);
    }

    public function rules(): array
    {
        $permissionId = $this->route('permission')?->id ?? null;
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($permissionId)],
            'key' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'guard_name' => ['required', 'in:web'],
            'is_mutable' => ['nullable', 'boolean'],
        ];
    }
}
