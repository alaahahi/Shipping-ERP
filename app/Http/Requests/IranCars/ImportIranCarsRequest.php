<?php

namespace App\Http\Requests\IranCars;

use App\Enums\IranBorder;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportIranCarsRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'border' => ['nullable', 'string', Rule::enum(IranBorder::class)],
        ];
    }
}
