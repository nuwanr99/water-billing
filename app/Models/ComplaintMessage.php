<?php

namespace App\Models;

use Database\Factories\ComplaintMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $complaint_id
 * @property int|null $user_id
 * @property string $body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Complaint $complaint
 * @property-read User|null $author
 * @property-read Collection<int, Attachment> $attachments
 */
#[Fillable(['complaint_id', 'user_id', 'body'])]
class ComplaintMessage extends Model
{
    /** @use HasFactory<ComplaintMessageFactory> */
    use HasFactory;

    /**
     * The complaint the message belongs to.
     *
     * @return BelongsTo<Complaint, $this>
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
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
     * Evidence attached to the message.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Whether this is a system-generated message.
     */
    public function isSystem(): bool
    {
        return $this->user_id === null;
    }
}
