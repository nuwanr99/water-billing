<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWaterAccountRequest;
use App\Http\Requests\Admin\UpdateWaterAccountRequest;
use App\Libraries\Datatable;
use App\Models\AccountLedgerEntry;
use App\Models\BillingCategory;
use App\Models\User;
use App\Models\WaterAccount;
use App\Models\WaterAccountBalance;
use App\Services\AccountLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class WaterAccountController extends Controller
{
    /**
     * Show the water accounts list.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['account_number', 'meter_number', 'owner.first_name', 'owner.last_name'],
            orderColumns: [
                'account_number',
                'status',
                'connected_at',
                'created_at',
                'owner' => fn (Builder $query, string $direction) => $query->orderBy(
                    User::select('first_name')->whereColumn('users.id', 'water_accounts.user_id'),
                    $direction === 'desc' ? 'desc' : 'asc',
                ),
                'balance' => fn (Builder $query, string $direction) => $query->orderBy(
                    WaterAccountBalance::select('balance')->whereColumn('water_account_balances.water_account_id', 'water_accounts.id'),
                    $direction === 'desc' ? 'desc' : 'asc',
                ),
            ],
            defaultSort: 'created_at',
            defaultDirection: 'desc',
        );

        $waterAccounts = $datatable
            ->paginate(WaterAccount::query()->with(['owner:id,first_name,last_name', 'billingCategory:id,name', 'balanceRecord']))
            ->through(fn (WaterAccount $waterAccount): array => [
                'id' => $waterAccount->id,
                'account_number' => $waterAccount->account_number,
                'meter_number' => $waterAccount->meter_number,
                'owner' => [
                    'id' => $waterAccount->owner->id,
                    'name' => $waterAccount->owner->name,
                ],
                'billing_category' => $waterAccount->billingCategory?->name,
                'status' => $waterAccount->status->value,
                'balance' => (float) ($waterAccount->balanceRecord->balance ?? 0),
                'connected_at' => $waterAccount->connected_at?->toFormattedDateString(),
                'created_at' => $waterAccount->created_at?->toFormattedDateString(),
            ]);

        return Inertia::render('admin/water-accounts/Index', [
            'waterAccounts' => $waterAccounts,
            'filters' => $datatable->filters(),
        ]);
    }

    /**
     * Search members to assign as the water account owner.
     */
    public function owners(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->value();

        $owners = User::query()
            ->role('Member')
            ->when($search !== '', function (Builder $query) use ($search) {
                $term = "%{$search}%";

                $query->where(fn (Builder $subQuery) => $subQuery
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

        return response()->json($owners);
    }

    /**
     * Show the account's ledger statement: every financial event with
     * running balances (D-16), newest day first so the latest activity is
     * on page one. Append-only — display only.
     */
    public function statement(WaterAccount $waterAccount, AccountLedgerService $accountLedger): Response
    {
        $waterAccount->load('owner:id,first_name,last_name');

        $entries = $waterAccount->ledgerEntries()
            ->with('recorder:id,first_name,last_name')
            ->reorder()
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AccountLedgerEntry $entry): array => [
                'id' => $entry->id,
                'type' => $entry->entry_type->value,
                'date' => $entry->entry_date->format('d M Y'),
                'billing_month' => $entry->billing_month,
                'document_number' => $entry->document_number,
                'description' => $entry->description,
                'amount' => (float) $entry->amount,
                'running_balance' => (float) $entry->running_balance,
                'recorded_by' => $entry->recorder?->name,
            ]);

        return Inertia::render('admin/water-accounts/Statement', [
            'account' => [
                'id' => $waterAccount->id,
                'owner_name' => $waterAccount->owner->name,
                'account_number' => $waterAccount->account_number,
                'meter_number' => $waterAccount->meter_number,
                'balance' => $accountLedger->balanceFor($waterAccount),
            ],
            'entries' => $entries,
        ]);
    }

    /**
     * Show the create water account page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/water-accounts/Create', [
            'suggestedAccountNumber' => $this->suggestAccountNumber(),
            'billingCategories' => $this->billingCategoryOptions(),
        ]);
    }

    /**
     * Store a new water account.
     */
    public function store(StoreWaterAccountRequest $request): RedirectResponse
    {
        WaterAccount::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Water account created.')]);

        return to_route('admin.water-accounts.index');
    }

    /**
     * Show the edit water account page.
     */
    public function edit(WaterAccount $waterAccount): Response
    {
        $waterAccount->load('owner:id,first_name,last_name,email');

        return Inertia::render('admin/water-accounts/Edit', [
            'billingCategories' => $this->billingCategoryOptions($waterAccount->billing_category_id),
            'waterAccount' => [
                'id' => $waterAccount->id,
                'billing_category_id' => $waterAccount->billing_category_id,
                'account_number' => $waterAccount->account_number,
                'meter_number' => $waterAccount->meter_number,
                'initial_reading' => (float) $waterAccount->initial_reading,
                'connection_address' => $waterAccount->connection_address,
                'status' => $waterAccount->status->value,
                'connected_at' => $waterAccount->connected_at?->toDateString(),
                'owner' => [
                    'id' => $waterAccount->owner->id,
                    'name' => $waterAccount->owner->name,
                    'email' => $waterAccount->owner->email,
                ],
            ],
        ]);
    }

    /**
     * Update the given water account.
     *
     * The first recorded reading derives its consumption from the initial
     * (baseline) reading, so a baseline change re-derives it atomically.
     */
    public function update(UpdateWaterAccountRequest $request, WaterAccount $waterAccount): RedirectResponse
    {
        DB::transaction(function () use ($request, $waterAccount) {
            $waterAccount->update($request->validated());

            if (! $waterAccount->wasChanged('initial_reading')) {
                return;
            }

            $firstReading = $waterAccount->readings()->orderBy('billing_month')->first();
            $firstReading?->update([
                'consumption' => round((float) $firstReading->reading_value - (float) $waterAccount->initial_reading, 2),
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Water account updated.')]);

        return to_route('admin.water-accounts.index');
    }

    /**
     * The billing categories selectable on the account form: active ones,
     * plus the account's current category even when deactivated.
     *
     * @return array<int, array{id: int, name: string}>
     */
    protected function billingCategoryOptions(?int $currentId = null): array
    {
        return BillingCategory::query()
            ->where('is_active', true)
            ->when($currentId !== null, fn (Builder $query) => $query->orWhere('id', $currentId))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BillingCategory $billingCategory): array => [
                'id' => $billingCategory->id,
                'name' => $billingCategory->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Suggest the next account number in the society numbering scheme.
     */
    protected function suggestAccountNumber(): string
    {
        $lastNumber = WaterAccount::query()
            ->where('account_number', 'like', 'ACC-%')
            ->orderByDesc('id')
            ->value('account_number');

        $next = $lastNumber === null ? 1 : (int) str_replace('ACC-', '', $lastNumber) + 1;

        return 'ACC-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
