<?php

namespace App\Services;

use App\Models\BillingCategory;
use App\Models\TariffTier;
use Illuminate\Database\Eloquent\Collection;

class TariffCalculationService
{
    /**
     * Price a month's consumption against a category's tariff.
     *
     * Walks the ordered slabs allocating consumption across the half-open
     * ranges (lower, upper], so fractional units land in the correct slab.
     * The monthly service charge is the one attached to the slab the total
     * consumption falls in — zero consumption pays the first slab's charge
     * (the minimum monthly charge).
     *
     * The returned breakdown is the snapshot bills persist at generation
     * time (D-06); late fees and outstanding balances are applied by the
     * billing run, which has the bill context this service deliberately
     * lacks.
     *
     * @return array{
     *     consumption: float,
     *     tiers: list<array{lower_units: int, upper_units: int|null, units: float, rate_per_unit: float, amount: float}>,
     *     usage_charge: float,
     *     service_charge: float,
     *     total: float,
     * }
     */
    public function calculate(BillingCategory $category, float $consumption): array
    {
        $tiers = $category->tiers()->get();

        $breakdown = [];
        $usageCharge = 0.0;

        foreach ($tiers as $tier) {
            $allocated = $this->unitsWithinTier($tier, $consumption);

            if ($allocated <= 0) {
                continue;
            }

            $amount = round($allocated * (float) $tier->rate_per_unit, 2);
            $usageCharge += $amount;

            $breakdown[] = [
                'lower_units' => $tier->lower_units,
                'upper_units' => $tier->upper_units,
                'units' => $allocated,
                'rate_per_unit' => (float) $tier->rate_per_unit,
                'amount' => $amount,
            ];
        }

        $containingTier = $this->tierContaining($tiers, $consumption);
        $serviceCharge = $containingTier === null ? 0.0 : (float) $containingTier->service_charge;
        $usageCharge = round($usageCharge, 2);

        return [
            'consumption' => $consumption,
            'tiers' => $breakdown,
            'usage_charge' => $usageCharge,
            'service_charge' => $serviceCharge,
            'total' => round($usageCharge + $serviceCharge, 2),
        ];
    }

    /**
     * The number of units of the given consumption that fall inside the
     * slab's (lower, upper] range.
     */
    protected function unitsWithinTier(TariffTier $tier, float $consumption): float
    {
        if ($consumption <= $tier->lower_units) {
            return 0.0;
        }

        $cappedAt = $tier->upper_units === null
            ? $consumption
            : min($consumption, (float) $tier->upper_units);

        return round($cappedAt - $tier->lower_units, 2);
    }

    /**
     * The slab the month's total consumption falls in, which carries the
     * applicable monthly service charge. Zero consumption falls in the
     * first slab.
     *
     * @param  Collection<int, TariffTier>  $tiers  ordered by lower_units
     */
    protected function tierContaining(Collection $tiers, float $consumption): ?TariffTier
    {
        foreach ($tiers as $tier) {
            if ($tier->upper_units === null || $consumption <= $tier->upper_units) {
                return $tier;
            }
        }

        return null;
    }
}
