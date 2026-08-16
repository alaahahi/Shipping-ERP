<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class DestroyCompanyCmrFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $cmr = strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $this->input('cmr_key', ''))));
        $this->merge([
            'cmr_key' => mb_substr($cmr, 0, 80),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cmr_key' => ['nullable', 'string', 'max:80'],
        ];
    }
}
