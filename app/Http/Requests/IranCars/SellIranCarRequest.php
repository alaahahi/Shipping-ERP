<?php

namespace App\Http\Requests\IranCars;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class SellIranCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::IranCarsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sale_price' => ['required', 'numeric', 'min:0'],
            'sold_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
