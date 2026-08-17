<?php

namespace App\Http\Requests\Settings;

use App\Enums\AppLocale;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company.name' => ['required', 'string', 'max:255'],
            'company.email' => ['nullable', 'email', 'max:255'],
            'company.phone' => ['nullable', 'string', 'max:50'],
            'company.address' => ['nullable', 'string', 'max:500'],
            'company.logo' => ['nullable', 'image', 'max:4096'],
            'company.remove_logo' => ['sometimes', 'boolean'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['sometimes', 'boolean'],
            'app.timezone' => ['required', 'string', 'timezone'],
            'app.locale' => ['required', 'string', Rule::in(AppLocale::values())],
            'app.currency' => ['required', 'string', Rule::in(['USD', 'AED', 'IQD', 'EUR'])],
            'whatsapp.tenant_id' => ['required', 'string', 'max:120'],
            'whatsapp.enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company.name' => 'company name',
            'company.email' => 'company email',
            'company.phone' => 'company phone',
            'company.address' => 'company address',
            'app.timezone' => 'timezone',
            'app.locale' => 'locale',
            'app.currency' => 'currency',
            'whatsapp.tenant_id' => 'WhatsApp tenant ID',
            'whatsapp.enabled' => 'WhatsApp enabled',
        ];
    }
}
