<?php

namespace App\Policies;

use App\Models\Complaint;
use App\Models\User;

/**
 * Per-record complaint access (D-50). Route middleware still gates the
 * pages; this decides who may see and act on a specific ticket. Super Admin
 * is short-circuited in AppServiceProvider's Gate::before.
 */
class ComplaintPolicy
{
    /**
     * View a specific complaint: the member who owns it, a handler, or any
     * staff member allowed to see all complaints.
     */
    public function view(User $user, Complaint $complaint): bool
    {
        return $this->owns($user, $complaint)
            || $user->can('complaints.view-all')
            || $this->handles($user, $complaint);
    }

    /**
     * Reply to a live complaint: the owner, a handler, or a manager.
     */
    public function reply(User $user, Complaint $complaint): bool
    {
        if (! $complaint->status->isLive()) {
            return false;
        }

        return $this->owns($user, $complaint)
            || $this->handles($user, $complaint)
            || $user->can('complaints.manage');
    }

    /**
     * Assign handlers: a manager only.
     */
    public function assign(User $user, Complaint $complaint): bool
    {
        return $user->can('complaints.manage');
    }

    /**
     * Close a live complaint: the owning member (self-close) or a manager.
     */
    public function close(User $user, Complaint $complaint): bool
    {
        if (! $complaint->status->isLive()) {
            return false;
        }

        return $this->owns($user, $complaint) || $user->can('complaints.manage');
    }

    /**
     * Whether the user submitted the complaint.
     */
    protected function owns(User $user, Complaint $complaint): bool
    {
        return $complaint->user_id === $user->id;
    }

    /**
     * Whether the user is one of the complaint's handlers.
     */
    protected function handles(User $user, Complaint $complaint): bool
    {
        return $complaint->handlers()->whereKey($user->id)->exists();
    }
}
