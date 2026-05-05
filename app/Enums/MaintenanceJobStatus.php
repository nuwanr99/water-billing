<?php

namespace App\Enums;

enum MaintenanceJobStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Whether the job is still open for work and updates.
     */
    public function isLive(): bool
    {
        return $this === self::Assigned || $this === self::InProgress;
    }
}
