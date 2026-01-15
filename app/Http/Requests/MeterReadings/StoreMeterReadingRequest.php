<?php

namespace App\Http\Requests\MeterReadings;

use App\Models\WaterAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMeterReadingRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reading_value' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('reading_value')) {
                    return;
                }

                /** @var WaterAccount $waterAccount */
                $waterAccount = $this->route('waterAccount');

                if ($waterAccount->readings()->where('billing_month', now()->format('Y-m'))->exists()) {
                    $validator->errors()->add('reading_value', __('A reading for :month is already recorded for this account.', [
                        'month' => now()->format('F Y'),
                    ]));

                    return;
                }

                $previousValue = $waterAccount->previousMeterValue();

                if ($this->float('reading_value') < $previousValue) {
                    $validator->errors()->add('reading_value', __('The reading must not be lower than the previous value (:previous).', [
                        'previous' => number_format($previousValue, 2),
                    ]));
                }
            },
        ];
    }
}
