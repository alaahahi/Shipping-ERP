<?php

namespace App\Http\Requests\IranCars;

use App\Enums\IranBorder;
use App\Enums\IranCarSaleState;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmIranCarsImportRequest extends FormRequest
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
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'border' => ['nullable', 'string', Rule::enum(IranBorder::class)],
            'sale_state' => ['required', 'string', Rule::enum(IranCarSaleState::class)],
        ];
    }
}
