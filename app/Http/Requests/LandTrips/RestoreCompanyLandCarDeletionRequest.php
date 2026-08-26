<?php

namespace App\Http\Requests\LandTrips;

use App\Models\LandTripCarDeletion;
use Illuminate\Foundation\Http\FormRequest;

class RestoreCompanyLandCarDeletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restore', LandTripCarDeletion::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'deletion_id' => ['required', 'integer', 'exists:land_trip_car_deletions,id'],
            'item_ids' => ['nullable', 'array'],
            'item_ids.*' => ['integer', 'exists:land_trip_car_deletion_items,id'],
        ];
    }
}
