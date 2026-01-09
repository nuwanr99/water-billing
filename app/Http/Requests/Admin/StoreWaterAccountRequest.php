<?php

namespace App\Http\Requests\Admin;

use App\Enums\WaterAccountStatus;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWaterAccountRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'account_number' => ['required', 'string', 'max:255', Rule::unique('water_accounts')],
            'meter_number' => ['required', 'string', 'max:255'],
            'connection_address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(WaterAccountStatus::class)],
            'connected_at' => ['nullable', 'date'],
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
                if ($validator->errors()->has('user_id')) {
                    return;
                }

                if (! User::find($this->integer('user_id'))?->hasRole('Member')) {
                    $validator->errors()->add('user_id', __('The owner must be a member.'));
                }
            },
        ];
    }
}
