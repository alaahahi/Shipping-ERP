<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\LandTripStatus;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionLandTripRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(LandTripStatus::class)],
        ];
    }
}
