<?php

namespace App\Enums;

/**
 * A stock movement's kind, which fixes the sign of its quantity (D-45, M7):
 * a purchase adds stock, a usage removes it, an adjustment corrects it either
 * way.
 */
enum StockMovementType: string
{
    case Purchase = 'purchase';
    case Usage = 'usage';
    case Adjustment = 'adjustment';

    /**
     * A human label for the movement type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Usage => 'Usage',
            self::Adjustment => 'Adjustment',
        };
    }

    /**
     * Turn a user-entered, unsigned magnitude into the signed change this
     * type applies to stock. A purchase adds, a usage subtracts; an
     * adjustment carries its own sign and is passed through untouched.
     */
    public function signedQuantity(int $quantity): int
    {
        return match ($this) {
            self::Purchase => abs($quantity),
            self::Usage => -abs($quantity),
            self::Adjustment => $quantity,
        };
    }
}
