<?php

namespace App\Enums;

/**
 * The only four bill statuses (spec §5.3 hard rule). The generated →
 * approved transition is automatic inside the generation transaction (D-15);
 * no user approves bills.
 */
enum BillStatus: string
{
    case Generated = 'generated';
    case Approved = 'approved';
    case Paid = 'paid';
    case Overdue = 'overdue';
}
