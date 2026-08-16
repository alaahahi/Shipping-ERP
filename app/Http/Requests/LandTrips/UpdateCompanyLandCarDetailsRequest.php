<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyLandCarDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'model' => ['sometimes', 'nullable', 'string', 'max:180'],
            'color' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
