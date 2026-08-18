<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\LandDriverPaymentType;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLandDriverPaymentRequest extends FormRequest
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
            'driver_name' => ['required', 'string', 'max:180'],
            'cmr_number' => ['nullable', 'string', 'max:80'],
            'cars_count' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::enum(LandDriverPaymentType::class)],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
