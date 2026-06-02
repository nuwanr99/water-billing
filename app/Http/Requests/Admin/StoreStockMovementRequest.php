<?php

namespace App\Http\Requests\Admin;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
{
    /**
     * Route middleware already gates this with `inventory.record-movement`.
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
            'movement_type' => ['required', Rule::enum(StockMovementType::class)],
            'quantity' => ['required', 'integer', 'between:-1000000,1000000'],
            'unit_rate' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100000000'],
            'maintenance_job_id' => ['nullable', Rule::exists('maintenance_jobs', 'id')],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Enforce the per-type sign rules and that the movement can never take an
     * item's on-hand count below zero.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $type = StockMovementType::tryFrom((string) $this->input('movement_type'));
                $quantity = (int) $this->input('quantity');

                if ($type === null) {
                    return;
                }

                if ($type === StockMovementType::Adjustment) {
                    if ($quantity === 0) {
                        $validator->errors()->add('quantity', __('An adjustment must be a non-zero amount (use a minus sign to reduce stock).'));
                    }

                    if (trim((string) $this->input('note')) === '') {
                        $validator->errors()->add('note', __('A note is required to explain an adjustment.'));
                    }
                } elseif ($quantity < 1) {
                    $validator->errors()->add('quantity', __('Enter a quantity of at least 1.'));
                }

                /** @var InventoryItem $item */
                $item = $this->route('inventoryItem');
                $resulting = $item->quantity_in_stock + $type->signedQuantity($quantity);

                if ($resulting < 0) {
                    $validator->errors()->add('quantity', __('Only :count :unit in stock — this movement would take it below zero.', [
                        'count' => $item->quantity_in_stock,
                        'unit' => $item->unit,
                    ]));
                }
            },
        ];
    }
}
