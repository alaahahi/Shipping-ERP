<?php

namespace App\Http\Requests\Roles;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::RolesManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = array_map(
            static fn (Permission $permission): string => $permission->value,
            Permission::cases()
        );

        return [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', Rule::in($allowed)],
        ];
    }
}
