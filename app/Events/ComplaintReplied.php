<?php

namespace App\Events;

use App\Models\ComplaintMessage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a reply is posted to a complaint thread. The listener routes
 * the notification by author: member replies reach the staff participants,
 * staff replies reach the member (§4).
 */
class ComplaintReplied implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public ComplaintMessage $message) {}
}
