<?php

namespace App\Listeners;

use App\Events\MaintenanceJobCancelled;
use App\Jobs\SendJobNotification;
use App\Models\User;

/**
 * Notifies the assignees that a job has been cancelled.
 */
class SendJobCancelledNotification
{
    /**
     * Handle the event.
     */
    public function handle(MaintenanceJobCancelled $event): void
    {
        $job = $event->job;
        $message = "නඩත්තු කාර්යය ({$job->job_number}) අවලංගු කරන ලදී.".
            ($event->notes !== null && $event->notes !== '' ? "\n{$event->notes}" : '');

        $job->assignees()->get()
            ->each(fn (User $assignee) => SendJobNotification::dispatch($assignee, $message));
    }
}
