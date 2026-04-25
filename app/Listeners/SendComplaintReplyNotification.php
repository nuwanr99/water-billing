<?php

namespace App\Listeners;

use App\Events\ComplaintReplied;
use App\Services\ComplaintNotifier;
use Illuminate\Support\Str;

/**
 * Routes a thread reply (§4): a member's reply reaches the staff
 * participants; a staff reply reaches the member.
 */
class SendComplaintReplyNotification
{
    public function __construct(protected ComplaintNotifier $notifier) {}

    /**
     * Handle the event.
     */
    public function handle(ComplaintReplied $event): void
    {
        $message = $event->message;
        $complaint = $message->complaint->loadMissing('member');
        $excerpt = Str::limit((string) $message->body, 120);

        if ($message->user_id === $complaint->user_id) {
            $this->notifier->notify(
                $this->notifier->staffParticipants($complaint),
                "පැමිණිල්ල {$complaint->complaint_number} සඳහා නව පිළිතුරක්.\n{$excerpt}",
                excludeUserId: $message->user_id,
            );

            return;
        }

        $this->notifier->notify(
            [$complaint->member],
            "ඔබගේ පැමිණිල්ල ({$complaint->complaint_number}) යාවත්කාලීන විය.\n{$excerpt}",
        );
    }
}
