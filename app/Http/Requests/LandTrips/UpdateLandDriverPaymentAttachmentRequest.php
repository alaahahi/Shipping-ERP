<?php

namespace App\Http\Requests\LandTrips;

use App\Models\Company;
use App\Models\LandDriverPayment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLandDriverPaymentAttachmentRequest extends FormRequest
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
            'attachment' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ];
    }
}
