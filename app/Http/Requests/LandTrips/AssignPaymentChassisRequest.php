<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class AssignPaymentChassisRequest extends FormRequest
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
            'car_ids' => ['nullable', 'array'],
            'car_ids.*' => ['integer'],
            'chassis_text' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
