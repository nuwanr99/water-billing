<?php

namespace App\Http\Controllers;

use App\Enums\WaterAccountStatus;
use App\Models\WaterAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Resolves a scanned bill QR code (the same account + meter pair the
 * public pay flow reads) straight to the page the scanning workflow
 * needs. One server round-trip: Inertia follows the redirect, so the
 * staff member never sees an intermediate filtered list.
 */
class QrScanController extends Controller
{
    /**
     * Per context: the permissions its destination routes sit behind
     * (checked here so the resolver cannot leak past them) and the scan
     * origin a failed match returns to.
     *
     * @var array<string, array{permissions: list<string>, origin: string}>
     */
    protected const array CONTEXTS = [
        'meter-reading' => [
            'permissions' => ['readings.view'],
            'origin' => 'meter-readings.index',
        ],
        'payment' => [
            'permissions' => ['admin', 'payments.record-manual'],
            'origin' => 'admin.payments.collect',
        ],
    ];

    /**
     * Match the scanned pair to an active account and redirect to the
     * context's destination, or back to the scan origin with a toast.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'account' => ['required', 'string', 'max:50'],
            'meter' => ['required', 'string', 'max:50'],
            'context' => ['required', 'string', Rule::in(array_keys(self::CONTEXTS))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $context = self::CONTEXTS[$validated['context']];

        foreach ($context['permissions'] as $permission) {
            abort_unless($request->user()->can($permission), 403);
        }

        $waterAccount = WaterAccount::query()
            ->where('account_number', trim($validated['account']))
            ->where('meter_number', trim($validated['meter']))
            ->where('status', WaterAccountStatus::Active)
            ->first();

        if ($waterAccount === null) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The scanned code does not match an active account.')]);

            // Carry the page's active search filter back so a failed scan
            // does not silently reset what the user had typed.
            return to_route($context['origin'], array_filter(['search' => $validated['search'] ?? null]));
        }

        if ($validated['context'] === 'payment') {
            return to_route('admin.payments.collect.show', $waterAccount);
        }

        return $waterAccount->hasReadingForCurrentMonth()
            ? to_route('meter-readings.history', $waterAccount)
            : to_route('meter-readings.create', $waterAccount);
    }
}
