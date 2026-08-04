<?php

namespace App\Http\Requests\Voyages;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVoyageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $voyageId = $this->route('voyage')?->id;

        return [
            'ship_id' => ['required', 'integer', 'exists:ships,id'],
            'voyage_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('voyages', 'voyage_number')
                    ->where(fn ($query) => $query->where('ship_id', $this->integer('ship_id')))
                    ->ignore($voyageId),
            ],
            'sailing_date' => ['required', 'date'],
            'arrival_date' => ['nullable', 'date', 'after_or_equal:sailing_date'],
            'pol' => ['nullable', 'string', 'max:120'],
            'pod' => ['nullable', 'string', 'max:120'],
            'captain' => ['nullable', 'string', 'max:120'],
            'cost_per_car_aed' => ['nullable', 'numeric', 'min:0'],
            'captain_commission_aed' => ['nullable', 'numeric', 'min:0'],
            'purchase_price_aed' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
