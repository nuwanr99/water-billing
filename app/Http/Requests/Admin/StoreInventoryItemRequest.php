<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    /**
     * Route middleware already gates this with `inventory.manage`.
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
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'unit_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:100000000'],
            'quantity_in_stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reorder_level' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * Human-friendly field names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity_in_stock' => 'opening stock',
            'reorder_level' => 'reorder level',
            'unit_rate' => 'unit rate',
        ];
    }
}
