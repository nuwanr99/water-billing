<?php

namespace App\Listeners;

use App\Events\MaintenanceJobCompleted;
use App\Services\JobNotifier;
use Illuminate\Support\Str;

/**
 * Notifies the admin side to verify a completed job (§4, step 4). The member
 * is deliberately not told here — they hear at complaint closure.
 */
class SendJobCompletedNotification
{
    public function __construct(protected JobNotifier $notifier) {}

    /**
     * Handle the event.
     */
    public function handle(MaintenanceJobCompleted $event): void
    {
        $job = $event->job;
        $notes = Str::limit((string) $job->completion_notes, 160);

        $this->notifier->notify(
            $this->notifier->verifiers($job),
            "නඩත්තු කාර්යය ({$job->job_number}) නිම කර ඇත — සත්‍යාපනය කරන්න.\n{$notes}",
        );
    }
}
