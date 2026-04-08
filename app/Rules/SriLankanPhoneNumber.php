<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Accepts Sri Lankan phone numbers in three forms: +94 followed by nine
 * digits, ten digits starting with 0, or nine digits starting with 7.
 */
class SriLankanPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $number = preg_replace('/[\s().-]+/', '', (string) $value) ?? '';

        if (preg_match('/^(?:\+94\d{9}|0\d{9}|7\d{8})$/', $number) !== 1) {
            $fail(__('Enter a valid Sri Lankan phone number.'));
        }
    }
}
