<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJobStatusRequest extends FormRequest
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
            'status' => ['required', 'in:in_progress,completed,cancelled'],
            'notes' => ['nullable', 'required_if:status,completed,cancelled', 'string', 'max:2000'],
        ];
    }
}
