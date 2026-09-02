<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\CompanyWalletEntryType;
use App\Enums\Currency;
use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyWalletEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::LandTripsManage->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(CompanyWalletEntryType::class)],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'currency' => ['required', 'string', Rule::enum(Currency::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
