<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBillingCategoryRequest;
use App\Http\Requests\Admin\UpdateBillingCategoryRequest;
use App\Libraries\Datatable;
use App\Models\BillingCategory;
use App\Models\TariffTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BillingCategoryController extends Controller
{
    /**
     * Show the billing categories list.
     */
    public function index(Request $request): Response
    {
        $datatable = new Datatable(
            $request,
            searchColumns: ['name', 'description'],
            orderColumns: ['name', 'late_fee_percent', 'is_active', 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $billingCategories = $datatable
            ->paginate(BillingCategory::query()->withCount(['tiers', 'waterAccounts']))
            ->through(fn (BillingCategory $billingCategory): array => [
                'id' => $billingCategory->id,
                'name' => $billingCategory->name,
                'description' => $billingCategory->description,
                'late_fee_percent' => (float) $billingCategory->late_fee_percent,
                'is_active' => $billingCategory->is_active,
                'tiers_count' => $billingCategory->tiers_count,
                'water_accounts_count' => $billingCategory->water_accounts_count,
            ]);

        return Inertia::render('admin/billing-categories/Index', [
            'billingCategories' => $billingCategories,
            'filters' => $datatable->filters(),
        ]);
    }

    /**
     * Show the create billing category page.
     */
    public function create(): Response
    {
        return Inertia::render('admin/billing-categories/Create');
    }

    /**
     * Store a new billing category and its tariff slabs.
     */
    public function store(StoreBillingCategoryRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $billingCategory = BillingCategory::create($request->safe()->except('tiers'));

            $billingCategory->tiers()->createMany($request->validated('tiers'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Billing category created.')]);

        return to_route('admin.billing-categories.index');
    }

    /**
     * Show the edit billing category page.
     */
    public function edit(BillingCategory $billingCategory): Response
    {
        $billingCategory->load('tiers')->loadCount('waterAccounts');

        return Inertia::render('admin/billing-categories/Edit', [
            'billingCategory' => [
                'id' => $billingCategory->id,
                'name' => $billingCategory->name,
                'description' => $billingCategory->description,
                'late_fee_percent' => (float) $billingCategory->late_fee_percent,
                'is_active' => $billingCategory->is_active,
                'water_accounts_count' => $billingCategory->water_accounts_count,
                'tiers' => $billingCategory->tiers->map(fn (TariffTier $tier): array => [
                    'lower_units' => $tier->lower_units,
                    'upper_units' => $tier->upper_units,
                    'rate_per_unit' => (float) $tier->rate_per_unit,
                    'service_charge' => (float) $tier->service_charge,
                ]),
            ],
        ]);
    }

    /**
     * Update the given billing category, replacing its tariff slabs.
     *
     * Replacing rows wholesale is safe by design: bills snapshot their tier
     * breakdown at generation time and never reference tier ids (D-06).
     */
    public function update(UpdateBillingCategoryRequest $request, BillingCategory $billingCategory): RedirectResponse
    {
        DB::transaction(function () use ($request, $billingCategory) {
            $billingCategory->update($request->safe()->except('tiers'));

            $billingCategory->tiers()->delete();
            $billingCategory->tiers()->createMany($request->validated('tiers'));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Billing category updated.')]);

        return to_route('admin.billing-categories.index');
    }

    /**
     * Delete the given billing category and its tariff slabs.
     */
    public function destroy(BillingCategory $billingCategory): RedirectResponse
    {
        if ($billingCategory->waterAccounts()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This category is assigned to water accounts and cannot be deleted. Deactivate it instead.'),
            ]);

            return back();
        }

        $billingCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Billing category deleted.')]);

        return to_route('admin.billing-categories.index');
    }
}
