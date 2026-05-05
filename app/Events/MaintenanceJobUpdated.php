<?php

namespace App\Events;

use App\Models\JobUpdate;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an update is posted to a job thread. Every participant (other
 * assignees, the creator, the linked complaint's handlers) except the author
 * is notified (§4, step 3).
 */
class MaintenanceJobUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public JobUpdate $update) {}
}
