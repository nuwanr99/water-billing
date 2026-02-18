<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record a critical action (spec §7). Null user means the system —
     * scheduler or queued jobs.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(string $action, ?Model $auditable = null, array $context = [], ?User $user = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $user->id ?? auth()->id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'context' => $context === [] ? null : $context,
            'created_at' => now(),
        ]);
    }
}
