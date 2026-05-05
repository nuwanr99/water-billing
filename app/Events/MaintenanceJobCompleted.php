<?php

namespace App\Events;

use App\Models\MaintenanceJob;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a job is completed. The admin side — the linked complaint's
 * handlers, or the creator for an ad-hoc job — is notified to verify (§4,
 * step 4). The member is not told here; they hear at complaint closure.
 */
class MaintenanceJobCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public MaintenanceJob $job) {}
}
