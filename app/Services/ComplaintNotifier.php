<?php

namespace App\Services;

use App\Jobs\SendComplaintNotification;
use App\Models\Complaint;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who should hear about a complaint event and queues one WhatsApp
 * job per recipient (§4). The staff audience narrows with ownership: the
 * configured recipient list until a handler takes it, then the handler(s).
 * When the Jobs module lands, staffParticipants() also folds in the linked
 * job's assignees.
 */
class ComplaintNotifier
{
    public function __construct(protected Settings $settings) {}

    /**
     * The staff who should be notified about activity on the complaint.
     *
     * @return Collection<int, User>
     */
    public function staffParticipants(Complaint $complaint): Collection
    {
        $handlers = $complaint->handlers()->get();

        return $handlers->isNotEmpty()
            ? $handlers
            : $this->settings->complaintNotifyUsers();
    }

    /**
     * Queue the message to each recipient, skipping the actor who triggered
     * the event and de-duplicating by user id.
     *
     * @param  iterable<int, User>  $recipients
     */
    public function notify(iterable $recipients, string $message, ?int $excludeUserId = null): void
    {
        $seen = [];

        foreach ($recipients as $recipient) {
            if ($recipient->id === $excludeUserId || isset($seen[$recipient->id])) {
                continue;
            }

            $seen[$recipient->id] = true;

            SendComplaintNotification::dispatch($recipient, $message);
        }
    }
}
