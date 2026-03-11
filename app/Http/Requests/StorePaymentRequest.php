<?php

namespace App\Http\Requests;

use App\Enums\SystemLedgerAccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000000'],
            'destination_account_id' => [
                'required',
                Rule::exists('system_ledger_accounts', 'id')
                    ->where('is_active', true)
                    ->where('type', SystemLedgerAccountType::Asset->value),
            ],
            'reference' => ['nullable', 'string', 'max:100'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'destination_account_id.exists' => __('The destination must be an active asset account (cash or bank).'),
        ];
    }
}
