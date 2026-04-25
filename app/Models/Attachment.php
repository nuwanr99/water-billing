<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property int $uploaded_by
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $attachable
 * @property-read User $uploader
 */
#[Fillable(['attachable_type', 'attachable_id', 'uploaded_by', 'path', 'original_name', 'mime_type', 'size'])]
class Attachment extends Model
{
    /**
     * The message this file is attached to.
     *
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The user who uploaded the file.
     *
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Whether the attachment is a displayable image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
