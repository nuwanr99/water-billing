<?php

namespace App\Enums;

/**
 * The typed events on a water account's ledger (D-16, D-26). Amounts are
 * signed: positive = the member owes more, negative = reduces what they owe.
 * WaterCharge is the month's usage + service posting — one line item, never
 * "the bill" itself; it doubles as the bill's cutoff marker (D-25).
 */
enum AccountLedgerEntryType: string
{
    case WaterCharge = 'water_charge';
    case Charge = 'charge';
    case Penalty = 'penalty';
    case Adjustment = 'adjustment';
    case Payment = 'payment';
    case Reversal = 'reversal';
}
