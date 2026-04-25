<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignComplaintRequest extends FormRequest
{
    /**
     * Authorization is enforced by the policy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'handler_ids' => ['required', 'array', 'min:1'],
            'handler_ids.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
