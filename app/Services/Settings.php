<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Generic application-settings store (D-51): a cached key-value layer over
 * the settings table. Values are forever-cached and busted on write.
 */
class Settings
{
    protected const string CACHE_PREFIX = 'settings:';

    public const string COMPLAINT_NOTIFY_USER_IDS = 'complaints.notify_user_ids';

    /**
     * Read a setting, returning the default when it has never been set.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(
            self::CACHE_PREFIX.$key,
            fn () => Setting::query()->where('key', $key)->first()?->value,
        );

        return $value ?? $default;
    }

    /**
     * Write a setting and bust its cache.
     */
    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * The users configured to be notified when a complaint is raised (D-52).
     * Deleted users drop out silently.
     *
     * @return Collection<int, User>
     */
    public function complaintNotifyUsers(): Collection
    {
        $ids = (array) $this->get(self::COMPLAINT_NOTIFY_USER_IDS, []);

        if ($ids === []) {
            return new Collection;
        }

        return User::query()->whereIn('id', $ids)->get();
    }
}
