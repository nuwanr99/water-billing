<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Closed = 'closed';

    /**
     * Whether the complaint still accepts replies and status changes.
     */
    public function isLive(): bool
    {
        return $this !== self::Closed;
    }
}
