<?php

namespace App\Http\Controllers;

use App\Enums\WaterAccountStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the user dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $preferredId = $request->session()->get('current_water_account_id');
        $currentAccount = $user->resolveCurrentWaterAccount(is_int($preferredId) ? $preferredId : null);

        return Inertia::render('Dashboard', [
            'currentAccount' => $currentAccount === null ? null : [
                'id' => $currentAccount->id,
                'account_number' => $currentAccount->account_number,
                'meter_number' => $currentAccount->meter_number,
                'connection_address' => $currentAccount->connection_address,
                'status' => $currentAccount->status->value,
                'connected_at' => $currentAccount->connected_at?->toFormattedDateString(),
            ],
            'activeAccountsCount' => $user->waterAccounts
                ->where('status', WaterAccountStatus::Active)
                ->count(),
        ]);
    }
}
