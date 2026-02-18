<?php

namespace App\Events;

use App\Models\Bill;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired when a bill is generated at reading confirmation (D-14). Dispatched
 * only after the generation transaction commits. The notification listener
 * ships in Phase 5; until then the event is the integration point only.
 */
class BillGenerated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Bill $bill) {}
}
