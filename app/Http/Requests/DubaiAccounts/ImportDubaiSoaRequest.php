<?php

namespace App\Http\Requests\DubaiAccounts;

use Illuminate\Foundation\Http\FormRequest;

class ImportDubaiSoaRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }
}
