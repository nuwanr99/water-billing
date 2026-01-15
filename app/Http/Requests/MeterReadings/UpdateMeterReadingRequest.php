<?php

namespace App\Http\Requests\MeterReadings;

use App\Models\MeterReading;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMeterReadingRequest extends FormRequest
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

                /** @var MeterReading $meterReading */
                $meterReading = $this->route('meterReading');
                $previousValue = $meterReading->previousValue();

                if ($this->float('reading_value') < $previousValue) {
                    $validator->errors()->add('reading_value', __('The reading must not be lower than the previous value (:previous).', [
                        'previous' => number_format($previousValue, 2),
                    ]));
                }
            },
        ];
    }
}
