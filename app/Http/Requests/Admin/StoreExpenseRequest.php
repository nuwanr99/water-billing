<?php

namespace App\Http\Requests\Admin;

use App\Enums\SystemLedgerAccountType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Route middleware already gates this with `expenses.create`.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000000'],
            'category_account_id' => [
                'required',
                Rule::exists('system_ledger_accounts', 'id')
                    ->where('is_active', true)
                    ->where('type', SystemLedgerAccountType::Expense->value),
            ],
            'paid_from_account_id' => [
                'required',
                Rule::exists('system_ledger_accounts', 'id')
                    ->where('is_active', true)
                    ->where('type', SystemLedgerAccountType::Asset->value),
            ],
            'maintenance_job_id' => ['nullable', Rule::exists('maintenance_jobs', 'id')],
            'description' => ['required', 'string', 'max:1000'],
            'reference_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * Human-friendly names for the account fields.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'category_account_id' => 'expense category',
            'paid_from_account_id' => 'paid-from account',
        ];
    }
}
