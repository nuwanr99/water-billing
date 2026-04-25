<?php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a complaint is assigned to one or more handlers. Only the
 * newly added handlers are notified.
 */
class ComplaintAssigned implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  list<int>  $newHandlerIds
     */
    public function __construct(public Complaint $complaint, public array $newHandlerIds) {}
}
