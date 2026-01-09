<?php

namespace App\Models;

use App\Enums\WaterAccountStatus;
use Database\Factories\WaterAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $account_number
 * @property string $meter_number
 * @property string|null $connection_address
 * @property WaterAccountStatus $status
 * @property Carbon|null $connected_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $owner
 */
#[Fillable(['user_id', 'account_number', 'meter_number', 'connection_address', 'status', 'connected_at'])]
class WaterAccount extends Model
{
    /** @use HasFactory<WaterAccountFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => WaterAccountStatus::Active->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WaterAccountStatus::class,
            'connected_at' => 'date',
        ];
    }

    /**
     * Get the member who owns the water account.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
