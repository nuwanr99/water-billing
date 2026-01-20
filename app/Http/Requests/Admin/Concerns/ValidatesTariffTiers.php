<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

trait ValidatesTariffTiers
{
    /**
     * The per-field validation rules for the tariff slab rows.
     *
     * @return array<string, array<mixed>>
     */
    protected function tierRules(): array
    {
        return [
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.lower_units' => ['required', 'integer', 'min:0'],
            'tiers.*.upper_units' => ['nullable', 'integer', 'gt:tiers.*.lower_units'],
            'tiers.*.rate_per_unit' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'tiers.*.service_charge' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * Validates the slab structure as a whole: slabs start at 0, are
     * contiguous (each starts where the previous ends), and exactly the
     * last slab is open-ended.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $hasTierErrors = collect($validator->errors()->keys())
                    ->contains(fn (string $key): bool => $key === 'tiers' || Str::startsWith($key, 'tiers.'));

                if ($hasTierErrors) {
                    return;
                }

                /** @var array<array-key, array{lower_units: int|string, upper_units: int|string|null}> $tiers */
                $tiers = $this->input('tiers', []);
                $lastIndex = count($tiers) - 1;
                $expectedLower = 0;

                foreach (array_values($tiers) as $index => $tier) {
                    if ((int) $tier['lower_units'] !== $expectedLower) {
                        $validator->errors()->add(
                            "tiers.{$index}.lower_units",
                            __('Slabs must be contiguous: each slab starts where the previous one ends, from 0.'),
                        );

                        return;
                    }

                    if ($index === $lastIndex) {
                        if ($tier['upper_units'] !== null) {
                            $validator->errors()->add(
                                "tiers.{$index}.upper_units",
                                __('The last slab must be open-ended (leave its upper bound empty).'),
                            );
                        }

                        return;
                    }

                    if ($tier['upper_units'] === null) {
                        $validator->errors()->add(
                            "tiers.{$index}.upper_units",
                            __('Only the last slab can be open-ended.'),
                        );

                        return;
                    }

                    $expectedLower = (int) $tier['upper_units'];
                }
            },
        ];
    }
}
