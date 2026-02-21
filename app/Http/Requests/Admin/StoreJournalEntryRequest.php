<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:1000'],
            'entry_date' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.system_ledger_account_id' => [
                'required',
                Rule::exists('system_ledger_accounts', 'id')->where('is_active', true),
            ],
            'lines.*.debit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100000000'],
            'lines.*.credit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100000000'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The double-entry contract: each line is either a debit or a credit,
     * and the journal's debits must equal its credits.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $totalDebit = 0.0;
                $totalCredit = 0.0;

                foreach ($this->input('lines', []) as $index => $line) {
                    $debit = (float) ($line['debit'] ?? 0);
                    $credit = (float) ($line['credit'] ?? 0);

                    if (($debit > 0) === ($credit > 0)) {
                        $validator->errors()->add(
                            "lines.{$index}.debit",
                            __('Each line needs either a debit or a credit amount, not both.'),
                        );
                    }

                    $totalDebit += $debit;
                    $totalCredit += $credit;
                }

                if (abs($totalDebit - $totalCredit) >= 0.005) {
                    $validator->errors()->add('lines', __('The journal does not balance: debits are :debit and credits are :credit.', [
                        'debit' => number_format($totalDebit, 2),
                        'credit' => number_format($totalCredit, 2),
                    ]));
                }
            },
        ];
    }
}
