<?php

namespace App\Models;

use Database\Factories\JobUpdateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $maintenance_job_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MaintenanceJob $job
 * @property-read User|null $author
 * @property-read Collection<int, Attachment> $attachments
 */
#[Fillable(['maintenance_job_id', 'user_id', 'body'])]
class JobUpdate extends Model
{
    /** @use HasFactory<JobUpdateFactory> */
    use HasFactory;

    /**
     * The job the update belongs to.
     *
     * @return BelongsTo<MaintenanceJob, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(MaintenanceJob::class, 'maintenance_job_id');
    }

    /**
     * The author, or null for a system message.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Evidence attached to the update.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Whether this is a system-generated update.
     */
    public function isSystem(): bool
    {
        return $this->user_id === null;
    }
}
