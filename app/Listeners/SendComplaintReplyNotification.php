<?php

namespace App\Listeners;

use App\Events\ComplaintReplied;
use App\Services\ComplaintNotifier;
use Illuminate\Support\Str;

/**
 * Notifies every complaint participant except the author when a reply is
 * posted (§4): the member and the staff side (handlers plus any linked-job
 * assignees) each hear about the other's activity.
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

        $recipients = $this->notifier->staffParticipants($complaint)
            ->push($complaint->member)
            ->unique('id');

        $this->notifier->notify(
            $recipients,
            "පැමිණිල්ල ({$complaint->complaint_number}) යාවත්කාලීන විය.\n{$excerpt}",
            excludeUserId: $message->user_id,
        );
    }
}
