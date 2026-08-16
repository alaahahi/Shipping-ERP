<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class RenameCompanyCmrGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $normalize = static function (?string $value): string {
            $cleaned = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) ($value ?? ''))));

            return mb_substr($cleaned, 0, 80);
        };

        $this->merge([
            'from_cmr_key' => $normalize($this->input('from_cmr_key')),
            'to_cmr_key' => $normalize($this->input('to_cmr_key')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_cmr_key' => ['nullable', 'string', 'max:80'],
            'to_cmr_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
