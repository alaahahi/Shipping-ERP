<?php

namespace App\Http\Requests\LandTrips;

use App\Models\Company;
use App\Models\CompanyWalletEntry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyWalletAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        $entry = $this->route('entry');

        return $company instanceof Company
            && $entry instanceof CompanyWalletEntry
            && (int) $entry->company_id === (int) $company->id
            && ($this->user()?->can('update', $entry) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachment' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
