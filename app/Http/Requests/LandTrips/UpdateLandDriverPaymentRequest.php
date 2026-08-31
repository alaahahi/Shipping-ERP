<?php

namespace App\Http\Requests\LandTrips;

use App\Enums\LandDriverPaymentType;
use App\Models\Company;
use App\Models\LandDriverPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLandDriverPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        $payment = $this->route('payment');

        return $company instanceof Company
            && $payment instanceof LandDriverPayment
            && (int) $payment->company_id === (int) $company->id
            && ($this->user()?->can('update', $payment) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'driver_name' => ['required', 'string', 'max:180'],
            'cmr_number' => ['nullable', 'string', 'max:80'],
            'cars_count' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::enum(LandDriverPaymentType::class)],
        ];
    }
}
