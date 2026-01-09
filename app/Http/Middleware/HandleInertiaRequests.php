<?php

namespace App\Http\Middleware;

use App\Models\WaterAccount;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    ...$user->toArray(),
                    ...$user->getAuthRolesPermissions(),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'waterAccounts' => fn (): array => $user?->waterAccounts
                ->sortBy('account_number')
                ->values()
                ->map(fn (WaterAccount $waterAccount): array => [
                    'id' => $waterAccount->id,
                    'account_number' => $waterAccount->account_number,
                    'connection_address' => $waterAccount->connection_address,
                    'status' => $waterAccount->status->value,
                ])
                ->all() ?? [],
            'currentWaterAccountId' => function () use ($user, $request): ?int {
                $preferredId = $request->session()->get('current_water_account_id');

                return $user?->resolveCurrentWaterAccount(is_int($preferredId) ? $preferredId : null)?->id;
            },
        ];
    }
}
