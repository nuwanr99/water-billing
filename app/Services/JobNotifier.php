<?php

namespace App\Services;

use App\Jobs\SendJobNotification;
use App\Models\MaintenanceJob;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves who hears about a maintenance-job event and queues one WhatsApp
 * job per recipient (§4). Thread participants are the assignees, the creator,
 * and — for a linked job — the complaint's handlers.
 */
class JobNotifier
{
    /**
     * Everyone following the job's progress.
     *
     * @return Collection<int, User>
     */
    public function participants(MaintenanceJob $job): Collection
    {
        $participants = $job->assignees()->get()
            ->push($job->creator);

        if ($job->isLinked()) {
            $participants = $participants->concat($job->complaint->handlers()->get());
        }

        return $participants->unique('id')->values();
    }

    /**
     * The admin side that verifies a completed job: the linked complaint's
     * handlers, or the creator for an ad-hoc job.
     *
     * @return Collection<int, User>
     */
    public function verifiers(MaintenanceJob $job): Collection
    {
        $verifiers = $job->isLinked()
            ? $job->complaint->handlers()->get()
            : new Collection;

        return $verifiers->push($job->creator)->unique('id')->values();
    }

    /**
     * Queue the message to each recipient, skipping the actor and
     * de-duplicating by user id.
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

            SendJobNotification::dispatch($recipient, $message);
        }
    }
}
