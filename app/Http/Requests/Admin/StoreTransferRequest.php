<?php

namespace App\Http\Requests\Admin;

use App\Enums\SystemLedgerAccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A transfer moves money between the society's asset accounts (cash
     * box → bank and back); income/expense accounts are category buckets,
     * not places money sits, so they can never be a transfer endpoint.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assetAccount = fn () => Rule::exists('system_ledger_accounts', 'id')
            ->where('is_active', true)
            ->where('type', SystemLedgerAccountType::Asset->value);

        return [
            'from_account_id' => ['required', 'integer', 'different:to_account_id', $assetAccount()],
            'to_account_id' => ['required', 'integer', $assetAccount()],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000000'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
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
            'from_account_id.different' => __('The source and destination accounts must differ.'),
            'from_account_id.exists' => __('The source must be an active asset account (cash or bank).'),
            'to_account_id.exists' => __('The destination must be an active asset account (cash or bank).'),
        ];
    }
}
