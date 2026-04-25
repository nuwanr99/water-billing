<?php

namespace App\Listeners;

use App\Events\ComplaintAssigned;
use App\Jobs\SendComplaintNotification;
use App\Models\User;

/**
 * Notifies the newly added handler(s) that a complaint is now theirs.
 */
class SendComplaintAssignedNotification
{
    /**
     * Handle the event.
     */
    public function handle(ComplaintAssigned $event): void
    {
        $complaint = $event->complaint;
        $message = implode("\n", [
            'ඔබට පැමිණිල්ලක් ('.$complaint->complaint_number.') පවරා ඇත.',
            'විෂය: '.$complaint->subject,
        ]);

        User::query()->whereIn('id', $event->newHandlerIds)->get()
            ->each(fn (User $handler) => SendComplaintNotification::dispatch($handler, $message));
    }
}
