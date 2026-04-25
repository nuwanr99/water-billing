<?php

namespace App\Listeners;

use App\Events\ComplaintClosed;
use App\Services\ComplaintNotifier;

/**
 * Routes closure (§4): an admin closing notifies the member with the note;
 * a member self-closing notifies the staff participants.
 */
class SendComplaintClosedNotification
{
    public function __construct(protected ComplaintNotifier $notifier) {}

    /**
     * Handle the event.
     */
    public function handle(ComplaintClosed $event): void
    {
        $complaint = $event->complaint->loadMissing('member');

        if ($event->actor->id === $complaint->user_id) {
            $this->notifier->notify(
                $this->notifier->staffParticipants($complaint),
                "පැමිණිල්ල {$complaint->complaint_number} සාමාජිකයා විසින් වසා දමන ලදී.",
                excludeUserId: $event->actor->id,
            );

            return;
        }

        $this->notifier->notify(
            [$complaint->member],
            "ඔබගේ පැමිණිල්ල ({$complaint->complaint_number}) විසඳා වසා දමන ලදී.\n{$complaint->closure_note}",
        );
    }
}
