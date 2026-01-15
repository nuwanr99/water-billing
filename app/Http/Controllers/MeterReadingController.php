<?php

namespace App\Http\Controllers;

use App\Enums\WaterAccountStatus;
use App\Http\Requests\MeterReadings\StoreMeterReadingRequest;
use App\Http\Requests\MeterReadings\UpdateMeterReadingRequest;
use App\Models\MeterReading;
use App\Models\WaterAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Field interface for water controllers to record monthly meter readings.
 */
class MeterReadingController extends Controller
{
    /**
     * Show the active accounts as cards with their reading status.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->value();
        $currentMonth = now()->format('Y-m');

        $activeAccounts = WaterAccount::query()->where('status', WaterAccountStatus::Active);

        $accounts = (clone $activeAccounts)
            ->with(['owner:id,first_name,last_name', 'latestReading'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = "%{$search}%";

                $query->where(fn (Builder $subQuery) => $subQuery
                    ->where('account_number', 'like', $term)
                    ->orWhere('meter_number', 'like', $term)
                    ->orWhereHas('owner', fn (Builder $ownerQuery) => $ownerQuery
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)));
            })
            ->orderBy('account_number')
            ->get()
            ->map(function (WaterAccount $waterAccount) use ($currentMonth): array {
                $latest = $waterAccount->latestReading;
                $readThisMonth = $latest?->billing_month === $currentMonth;

                return [
                    ...$this->accountSummary($waterAccount),
                    'latest_reading' => $latest === null ? null : [
                        'value' => (float) $latest->reading_value,
                        'date' => $latest->reading_date->format('d M Y'),
                    ],
                    'read_this_month' => $readThisMonth,
                    'current_reading_id' => $readThisMonth ? $latest->id : null,
                ];
            });

        return Inertia::render('meter-readings/Index', [
            'accounts' => $accounts,
            'progress' => [
                'read' => (clone $activeAccounts)
                    ->whereHas('readings', fn (Builder $query) => $query->where('billing_month', $currentMonth))
                    ->count(),
                'total' => (clone $activeAccounts)->count(),
            ],
            'monthLabel' => now()->format('F Y'),
            'filters' => ['search' => $search === '' ? null : $search],
        ]);
    }

    /**
     * Show the reading entry form for an account.
     */
    public function create(WaterAccount $waterAccount): Response|RedirectResponse
    {
        $latest = $waterAccount->latestReading;

        if ($latest?->billing_month === now()->format('Y-m')) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This month\'s reading is already recorded.')]);

            return to_route('meter-readings.history', $waterAccount);
        }

        return Inertia::render('meter-readings/Create', [
            'account' => $this->accountSummary($waterAccount->load('owner')),
            'previous' => $latest === null
                ? ['value' => (float) $waterAccount->initial_reading, 'date' => null, 'is_initial' => true]
                : ['value' => (float) $latest->reading_value, 'date' => $latest->reading_date->format('d M Y'), 'is_initial' => false],
            'monthLabel' => now()->format('F Y'),
        ]);
    }

    /**
     * Record this month's reading; consumption is derived, never user input.
     */
    public function store(StoreMeterReadingRequest $request, WaterAccount $waterAccount): RedirectResponse
    {
        $consumption = round($request->float('reading_value') - $waterAccount->previousMeterValue(), 2);

        $waterAccount->readings()->create([
            'recorded_by' => $request->user()->id,
            'billing_month' => now()->format('Y-m'),
            'reading_value' => $request->float('reading_value'),
            'consumption' => $consumption,
            'reading_date' => now()->toDateString(),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':account — reading saved, :units units used.', [
                'account' => $waterAccount->account_number,
                'units' => number_format($consumption, 2),
            ]),
        ]);

        return to_route('meter-readings.index');
    }

    /**
     * Show the recorded readings for an account, newest first.
     */
    public function history(WaterAccount $waterAccount): Response
    {
        $latestId = $waterAccount->latestReading?->id;

        $readings = $waterAccount->readings()
            ->with('recorder:id,first_name,last_name')
            ->orderByDesc('billing_month')
            ->get()
            ->map(fn (MeterReading $reading): array => [
                'id' => $reading->id,
                'month' => Carbon::createFromFormat('Y-m', $reading->billing_month)->format('F Y'),
                'value' => (float) $reading->reading_value,
                'consumption' => (float) $reading->consumption,
                'recorded_by' => $reading->recorder->name,
                'date' => $reading->reading_date->format('d M Y'),
                'is_latest' => $reading->id === $latestId,
            ]);

        return Inertia::render('meter-readings/History', [
            'account' => $this->accountSummary($waterAccount->load('owner')),
            'readings' => $readings,
            'initialReading' => (float) $waterAccount->initial_reading,
        ]);
    }

    /**
     * Show the correction form; only the account's latest reading is editable.
     */
    public function edit(MeterReading $meterReading): Response
    {
        $waterAccount = $meterReading->waterAccount;

        abort_unless($meterReading->id === $waterAccount->latestReading?->id, 404);

        $previousReading = $meterReading->previousReading();

        return Inertia::render('meter-readings/Edit', [
            'account' => $this->accountSummary($waterAccount->load('owner')),
            'previous' => $previousReading === null
                ? ['value' => (float) $waterAccount->initial_reading, 'date' => null, 'is_initial' => true]
                : ['value' => (float) $previousReading->reading_value, 'date' => $previousReading->reading_date->format('d M Y'), 'is_initial' => false],
            'reading' => [
                'id' => $meterReading->id,
                'value' => (float) $meterReading->reading_value,
                'month' => Carbon::createFromFormat('Y-m', $meterReading->billing_month)->format('F Y'),
            ],
        ]);
    }

    /**
     * Correct the latest reading and re-derive its consumption.
     */
    public function update(UpdateMeterReadingRequest $request, MeterReading $meterReading): RedirectResponse
    {
        $waterAccount = $meterReading->waterAccount;

        abort_unless($meterReading->id === $waterAccount->latestReading?->id, 404);

        $meterReading->update([
            'reading_value' => $request->float('reading_value'),
            'consumption' => round($request->float('reading_value') - $meterReading->previousValue(), 2),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reading corrected.')]);

        return to_route('meter-readings.history', $waterAccount);
    }

    /**
     * @return array{id: int, owner_name: string, account_number: string, meter_number: string}
     */
    private function accountSummary(WaterAccount $waterAccount): array
    {
        return [
            'id' => $waterAccount->id,
            'owner_name' => $waterAccount->owner->name,
            'account_number' => $waterAccount->account_number,
            'meter_number' => $waterAccount->meter_number,
        ];
    }
}
