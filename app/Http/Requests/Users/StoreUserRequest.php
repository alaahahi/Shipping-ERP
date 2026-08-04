<?php

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Enums\SystemRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::UsersManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', 'string', Rule::in($this->allowedRoles())],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        return array_map(
            static fn (SystemRole $role): string => $role->value,
            SystemRole::cases()
        );
    }
}
