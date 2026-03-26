<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\WaterAccountStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $phone
 * @property string $address
 * @property string|null $wa_number
 * @property string $email
 * @property-read string $name
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $last_water_account_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['first_name', 'last_name', 'phone', 'address', 'wa_number', 'email', 'password', 'last_water_account_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
#[Appends(['name'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     *
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /**
     * Get the water accounts owned by the user.
     *
     * @return HasMany<WaterAccount, $this>
     */
    public function waterAccounts(): HasMany
    {
        return $this->hasMany(WaterAccount::class);
    }

    /**
     * Resolve the user's currently selected water account: the persisted
     * last-used account when it is still theirs, then the first active
     * account. The selection lives on the users table, not in the
     * session, so it survives logouts and follows the user across devices.
     */
    public function resolveCurrentWaterAccount(): ?WaterAccount
    {
        return $this->waterAccounts->firstWhere('id', $this->last_water_account_id)
            ?? $this->waterAccounts->firstWhere('status', WaterAccountStatus::Active)
            ?? $this->waterAccounts->first();
    }

    /**
     * Get the user's role names and all granted permission names.
     *
     * @return array{roles: Collection<int, string>, permissions: Collection<int, string>}
     */
    public function getAuthRolesPermissions(): array
    {
        return [
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
        ];
    }
}
