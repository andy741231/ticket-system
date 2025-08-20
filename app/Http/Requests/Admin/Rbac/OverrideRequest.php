<?php

namespace App\Http\Requests\Admin\Rbac;

use Illuminate\Foundation\Http\FormRequest;

class OverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Routes are guarded by middleware perm:admin.rbac.overrides.manage
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'team_id' => $this->team_id !== '' ? $this->team_id : null,
            'expires_at' => $this->expires_at !== '' ? $this->expires_at : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
            'team_id' => ['nullable', 'integer', 'exists:apps,id'],
            'effect' => ['required', 'in:allow,deny'],
            'reason' => ['nullable', 'string', 'max:500'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
