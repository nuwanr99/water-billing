<?php

namespace App\Listeners;

use App\Events\MaintenanceJobUpdated;
use App\Services\JobNotifier;
use Illuminate\Support\Str;

/**
 * Notifies every job participant except the author when an update is posted
 * (§4, step 3 — assignees and the admin both hear).
 */
class SendJobUpdatedNotification
{
    public function __construct(protected JobNotifier $notifier) {}

    /**
     * Handle the event.
     */
    public function handle(MaintenanceJobUpdated $event): void
    {
        $update = $event->update;
        $job = $update->job;
        $excerpt = Str::limit((string) $update->body, 120);

        $this->notifier->notify(
            $this->notifier->participants($job),
            "නඩත්තු කාර්යය ({$job->job_number}) යාවත්කාලීන විය.\n{$excerpt}",
            excludeUserId: $update->user_id,
        );
    }
}
