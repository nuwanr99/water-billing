<?php

namespace App\Enums;

/**
 * Manual payments are born Completed; Pending and Failed serve the PayHere
 * webhook lifecycle (D-33). Only completed payments have ledger postings.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
