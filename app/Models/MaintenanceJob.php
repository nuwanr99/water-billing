<?php

namespace App\Models;

use App\Enums\MaintenanceJobStatus;
use Database\Factories\MaintenanceJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $job_number
 * @property int|null $complaint_id
 * @property int $created_by
 * @property string $title
 * @property string $description
 * @property Carbon $scheduled_date
 * @property MaintenanceJobStatus $status
 * @property string|null $completion_notes
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Complaint|null $complaint
 * @property-read User $creator
 * @property-read Collection<int, User> $assignees
 * @property-read Collection<int, JobUpdate> $updates
 */
#[Fillable(['job_number', 'complaint_id', 'created_by', 'title', 'description', 'scheduled_date', 'status', 'completion_notes', 'completed_at'])]
class MaintenanceJob extends Model
{
    /** @use HasFactory<MaintenanceJobFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status' => MaintenanceJobStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The complaint this job resolves, if any (D-49).
     *
     * @return BelongsTo<Complaint, $this>
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }

    /**
     * The admin who raised the job — the "verify" recipient for ad-hoc jobs.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user(s) the job is assigned to (D-49).
     *
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'maintenance_job_assignees')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * The job thread, oldest first.
     *
     * @return HasMany<JobUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(JobUpdate::class)->oldest();
    }

    /**
     * Whether the job is attached to a complaint.
     */
    public function isLinked(): bool
    {
        return $this->complaint_id !== null;
    }
}
