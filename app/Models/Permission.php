<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property int $category_id
 * @property-read PermissionCategory $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Permission extends SpatiePermission
{
    /**
     * Get the category the permission belongs to.
     *
     * @return BelongsTo<PermissionCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PermissionCategory::class, 'category_id');
    }
}
