<?php

namespace App\Http\Requests\ShipExpenses;

use App\Models\Ship;
use App\Models\ShipExpense;
use App\Support\AttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShipExpenseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ship = $this->route('ship');
        $expense = $this->route('expense');

        return $ship instanceof Ship
            && $expense instanceof ShipExpense
            && (int) $expense->ship_id === (int) $ship->id
            && ($this->user()?->can('updateAttachment', $expense) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'attachment' => AttachmentRules::requiredFile(),
        ];
    }
}
