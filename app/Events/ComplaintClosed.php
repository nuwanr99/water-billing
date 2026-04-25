<?php

namespace App\Events;

use App\Models\Complaint;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a complaint is closed. An admin closing notifies the member
 * (with the closure note); a member self-closing notifies the staff
 * participants.
 */
class ComplaintClosed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public Complaint $complaint, public User $actor) {}
}
