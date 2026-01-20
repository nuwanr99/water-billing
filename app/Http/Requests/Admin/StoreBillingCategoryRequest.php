<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesTariffTiers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingCategoryRequest extends FormRequest
{
    use ValidatesTariffTiers;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('billing_categories')],
            'description' => ['nullable', 'string', 'max:255'],
            'late_fee_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'is_active' => ['required', 'boolean'],
            ...$this->tierRules(),
        ];
    }
}
