<?php

namespace App\Http\Requests\Admin;

use App\Enums\SystemLedgerAccountType;
use App\Models\SystemLedgerAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSystemLedgerAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var SystemLedgerAccount $systemLedgerAccount */
        $systemLedgerAccount = $this->route('systemLedgerAccount');

        return [
            'code' => ['required', 'string', 'max:10', 'regex:/^\d+$/', Rule::unique('system_ledger_accounts', 'code')->ignore($systemLedgerAccount)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(SystemLedgerAccountType::class)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Once journal lines reference the account, its code and type are frozen
     * — they define what historical journals mean.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var SystemLedgerAccount $systemLedgerAccount */
                $systemLedgerAccount = $this->route('systemLedgerAccount');

                if (! $systemLedgerAccount->lines()->exists()) {
                    return;
                }

                if ($this->input('code') !== $systemLedgerAccount->code) {
                    $validator->errors()->add('code', __('This account has journal entries; its code cannot change.'));
                }

                if ($this->input('type') !== $systemLedgerAccount->type->value) {
                    $validator->errors()->add('type', __('This account has journal entries; its type cannot change.'));
                }
            },
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
            'code.regex' => __('The account code must be numeric, e.g. 1000.'),
        ];
    }
}
