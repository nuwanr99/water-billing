<?php

namespace App\Events;

use App\Models\MaintenanceJob;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a job is created or reassigned. Only the newly added assignees
 * are notified.
 */
class MaintenanceJobAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $newAssigneeIds
     */
    public function __construct(public MaintenanceJob $job, public array $newAssigneeIds) {}
}
