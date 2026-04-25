<?php

namespace App\Http\Requests;

use App\Enums\ComplaintCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreComplaintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('complaints.submit') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'water_account_id' => ['nullable', 'integer', 'exists:water_accounts,id'],
            'category' => ['required', new Enum(ComplaintCategory::class)],
            'subject' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', Rule::file()->image()->max(5 * 1024), 'mimes:jpeg,jpg,png,webp'],
        ];
    }
}
