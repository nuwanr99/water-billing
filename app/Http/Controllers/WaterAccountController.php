<?php

namespace App\Http\Controllers;

use App\Models\WaterAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WaterAccountController extends Controller
{
    /**
     * Show the member's own water accounts.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $waterAccounts = $user->waterAccounts()
            ->orderBy('account_number')
            ->get()
            ->map(fn (WaterAccount $waterAccount): array => [
                'id' => $waterAccount->id,
                'account_number' => $waterAccount->account_number,
                'meter_number' => $waterAccount->meter_number,
                'connection_address' => $waterAccount->connection_address,
                'status' => $waterAccount->status->value,
                'connected_at' => $waterAccount->connected_at?->toFormattedDateString(),
            ]);

        return Inertia::render('water-accounts/Index', [
            'waterAccounts' => $waterAccounts,
        ]);
    }

    /**
     * Switch the member's currently selected water account.
     */
    public function switchTo(Request $request, WaterAccount $waterAccount): RedirectResponse
    {
        abort_unless($waterAccount->user_id === $request->user()->id, 403);

        $request->session()->put('current_water_account_id', $waterAccount->id);

        return back();
    }
}
