<?php

namespace App\Listeners;

use App\Events\MaintenanceJobAssigned;
use App\Jobs\SendJobNotification;
use App\Models\User;

/**
 * Notifies the newly assigned user(s) that a job is theirs.
 */
class SendJobAssignedNotification
{
    /**
     * Handle the event.
     */
    public function handle(MaintenanceJobAssigned $event): void
    {
        $job = $event->job;
        $message = implode("\n", [
            'ඔබට නව නඩත්තු කාර්යයක් ('.$job->job_number.') පවරා ඇත.',
            'කාර්යය: '.$job->title,
            'නියමිත දිනය: '.$job->scheduled_date->format('d M Y'),
        ]);

        User::query()->whereIn('id', $event->newAssigneeIds)->get()
            ->each(fn (User $assignee) => SendJobNotification::dispatch($assignee, $message));
    }
}
