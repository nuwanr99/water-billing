<?php

namespace App\Models;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintStatus;
use Database\Factories\ComplaintFactory;
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
 * @property string $complaint_number
 * @property int $user_id
 * @property int|null $water_account_id
 * @property ComplaintCategory $category
 * @property string $subject
 * @property string $description
 * @property ComplaintStatus $status
 * @property string|null $closure_note
 * @property int|null $closed_by
 * @property Carbon $submitted_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $member
 * @property-read WaterAccount|null $waterAccount
 * @property-read Collection<int, User> $handlers
 * @property-read Collection<int, ComplaintMessage> $messages
 */
#[Fillable(['complaint_number', 'user_id', 'water_account_id', 'category', 'subject', 'description', 'status', 'closure_note', 'closed_by', 'submitted_at', 'closed_at'])]
class Complaint extends Model
{
    /** @use HasFactory<ComplaintFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ComplaintCategory::class,
            'status' => ComplaintStatus::class,
            'submitted_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * The member who submitted the complaint.
     *
     * @return BelongsTo<User, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The water account the complaint concerns, if any.
     *
     * @return BelongsTo<WaterAccount, $this>
     */
    public function waterAccount(): BelongsTo
    {
        return $this->belongsTo(WaterAccount::class);
    }

    /**
     * The staff handlers who own the complaint (D-48).
     *
     * @return BelongsToMany<User, $this>
     */
    public function handlers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'complaint_assignees')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    /**
     * The thread messages, oldest first.
     *
     * @return HasMany<ComplaintMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ComplaintMessage::class)->oldest();
    }

    /**
     * Whether the complaint has at least one handler.
     */
    public function isAssigned(): bool
    {
        return $this->handlers()->exists();
    }
}
