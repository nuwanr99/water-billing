<?php

namespace App\Http\Controllers;

use App\Enums\WaterAccountStatus;
use App\Services\AccountLedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected AccountLedgerService $accountLedger,
    ) {}

    /**
     * Show the user dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $currentAccount = $user->resolveCurrentWaterAccount();
        $latestReading = $currentAccount?->latestReading;

        return Inertia::render('Dashboard', [
            'currentAccount' => $currentAccount === null ? null : [
                'id' => $currentAccount->id,
                'account_number' => $currentAccount->account_number,
                'meter_number' => $currentAccount->meter_number,
                'connection_address' => $currentAccount->connection_address,
                'status' => $currentAccount->status->value,
                'connected_at' => $currentAccount->connected_at?->toFormattedDateString(),
                'balance' => $this->accountLedger->balanceFor($currentAccount),
                'pay_url' => route('my.pay.show', $currentAccount),
            ],
            'latestReading' => $latestReading === null ? null : [
                'value' => (float) $latestReading->reading_value,
                'consumption' => (float) $latestReading->consumption,
                'date' => $latestReading->reading_date->toFormattedDateString(),
            ],
            'activeAccountsCount' => $user->waterAccounts
                ->where('status', WaterAccountStatus::Active)
                ->count(),
        ]);
    }
}
