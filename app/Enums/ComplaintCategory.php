<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    case Leak = 'leak';
    case Blockage = 'blockage';
    case LowPressure = 'low_pressure';
    case Other = 'other';

    /**
     * The English label shown to members and on WhatsApp messages.
     */
    public function label(): string
    {
        return match ($this) {
            self::Leak => 'Leak',
            self::Blockage => 'Blockage',
            self::LowPressure => 'Low pressure',
            self::Other => 'Other',
        };
    }
}
