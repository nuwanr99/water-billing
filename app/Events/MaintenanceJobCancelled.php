<?php

namespace App\Events;

use App\Models\MaintenanceJob;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a job is cancelled. The assignees are notified.
 */
class MaintenanceJobCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public MaintenanceJob $job, public ?string $notes) {}
}
