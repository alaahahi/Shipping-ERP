<?php

namespace App\Http\Requests\VoyageCars;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmVoyageCarsImportRequest extends FormRequest
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
            'run_async' => ['nullable', 'boolean'],
        ];
    }
}
