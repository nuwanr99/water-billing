<?php

namespace App\Listeners;

use App\Events\ComplaintSubmitted;
use App\Models\Complaint;
use App\Services\ComplaintNotifier;

/**
 * Notifies the configured recipient list when a complaint is raised (D-52).
 */
class SendComplaintSubmittedNotification
{
    public function __construct(protected ComplaintNotifier $notifier) {}

    /**
     * Handle the event.
     */
    public function handle(ComplaintSubmitted $event): void
    {
        $complaint = $event->complaint->loadMissing('member');

        $this->notifier->notify(
            $this->notifier->staffParticipants($complaint),
            $this->message($complaint),
        );
    }

    /**
     * The Sinhala alert sent to the recipient list.
     */
    protected function message(Complaint $complaint): string
    {
        return implode("\n", [
            'නව පැමිණිල්ලක් — '.$complaint->complaint_number,
            'සාමාජික: '.$complaint->member->name,
            'වර්ගය: '.$complaint->category->label(),
            'විෂය: '.$complaint->subject,
        ]);
    }
}
