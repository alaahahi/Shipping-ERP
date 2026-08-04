<?php

namespace App\Http\Requests\VoyageCars;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVoyageCarRequest extends FormRequest
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
        return [
            'voyage_company_id' => ['required', 'integer', 'exists:voyage_companies,id'],
            'chassis_no' => ['nullable', 'string', 'max:64'],
            'consignee_name' => ['required', 'string', 'max:180'],
            'shipper_name' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:255'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'code' => ['nullable', 'string', 'max:80'],
        ];
    }
}
