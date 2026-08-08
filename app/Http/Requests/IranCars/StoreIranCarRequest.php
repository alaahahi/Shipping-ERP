<?php

namespace App\Http\Requests\IranCars;

use App\Enums\IranBorder;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIranCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::IranCarsManage->value) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('vin')) {
            $this->merge([
                'vin' => strtoupper((string) preg_replace('/\s+/', '', trim((string) $this->input('vin')))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'border' => ['required', 'string', Rule::enum(IranBorder::class)],
            'vin' => ['required', 'string', 'max:64', 'unique:iran_cars,vin'],
            'model_name' => ['required', 'string', 'max:180'],
            'year' => ['nullable', 'integer', 'min:1980', 'max:2100'],
            'color' => ['nullable', 'string', 'max:80'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
