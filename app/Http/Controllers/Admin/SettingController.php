<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateNotificationSettingsRequest;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Application settings (D-51). Only the complaint-notification recipients
 * section ships now; the store and page are built to grow.
 */
class SettingController extends Controller
{
    public function __construct(protected Settings $settings) {}

    /**
     * Show the notifications settings page.
     */
    public function notifications(): Response
    {
        $ids = (array) $this->settings->get(Settings::COMPLAINT_NOTIFY_USER_IDS, []);

        $selected = User::query()->whereIn('id', $ids)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return Inertia::render('admin/settings/Notifications', [
            'complaintNotifyUsers' => $selected,
        ]);
    }

    /**
     * Save the complaint-notification recipients.
     */
    public function updateNotifications(UpdateNotificationSettingsRequest $request): RedirectResponse
    {
        $this->settings->set(
            Settings::COMPLAINT_NOTIFY_USER_IDS,
            array_values(array_unique(array_map('intval', $request->validated('complaint_notify_user_ids')))),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notification settings saved.')]);

        return redirect()->route('admin.settings.notifications.edit');
    }

    /**
     * Search users to add as notification recipients.
     */
    public function users(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $users = User::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = "%{$search}%";

                $query->where(fn (Builder $sub) => $sub
                    ->orWhere('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return response()->json($users);
    }
}
