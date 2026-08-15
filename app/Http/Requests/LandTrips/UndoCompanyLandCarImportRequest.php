<?php

namespace App\Http\Requests\LandTrips;

use App\Models\LandTripCarImport;
use Illuminate\Foundation\Http\FormRequest;

class UndoCompanyLandCarImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('undo', LandTripCarImport::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
