<?php

namespace App\Http\Requests\Admin;

use App\Enums\AccountLedgerEntryType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreChargeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                AccountLedgerEntryType::Charge->value,
                AccountLedgerEntryType::Penalty->value,
                AccountLedgerEntryType::Adjustment->value,
            ])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'not_in:0', 'between:-1000000,1000000'],
            'description' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Only adjustments may be negative — a credit in the member's favour;
     * charges and penalties always increase what the member owes.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('type') !== AccountLedgerEntryType::Adjustment->value && $this->float('amount') <= 0) {
                    $validator->errors()->add('amount', __('Charges and penalties must be a positive amount.'));
                }
            },
        ];
    }
}
