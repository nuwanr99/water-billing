<?php

namespace App\Events;

use App\Models\Complaint;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a member submits a complaint. Listened to by
 * SendComplaintSubmittedNotification, which notifies the configured
 * recipient list (D-52).
 */
class ComplaintSubmitted implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Complaint $complaint) {}
}
