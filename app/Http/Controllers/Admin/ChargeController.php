<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AccountLedgerEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChargeRequest;
use App\Models\AccountLedgerEntry;
use App\Models\WaterAccount;
use App\Services\AccountLedgerService;
use App\Services\AuditLogger;
use App\Services\RunningNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Custom charges (D-18): one-off/repair charges, penalties, and signed
 * adjustments recorded straight onto the account ledger, each with a
 * printable note. They affect the balance immediately and are presented
 * itemized on the account's next bill (D-25).
 */
class ChargeController extends Controller
{
    /**
     * Show the add-charge page for an account.
     */
    public function create(WaterAccount $waterAccount, AccountLedgerService $accountLedger): Response
    {
        $waterAccount->load('owner');

        return Inertia::render('admin/charges/Create', [
            'account' => [
                'id' => $waterAccount->id,
                'owner_name' => $waterAccount->owner->name,
                'account_number' => $waterAccount->account_number,
                'balance' => $accountLedger->balanceFor($waterAccount),
            ],
        ]);
    }

    /**
     * Post the charge to the account ledger and land on its printable note.
     */
    public function store(
        StoreChargeRequest $request,
        WaterAccount $waterAccount,
        AccountLedgerService $accountLedger,
        RunningNumberService $runningNumbers,
        AuditLogger $audit,
    ): RedirectResponse {
        $entry = DB::transaction(function () use ($request, $waterAccount, $accountLedger, $runningNumbers, $audit): AccountLedgerEntry {
            $entry = $accountLedger->post(
                $waterAccount,
                AccountLedgerEntryType::from($request->validated('type')),
                $request->float('amount'),
                $request->validated('description'),
                recordedBy: $request->user(),
                documentNumber: $runningNumbers->next('charge'),
            );

            $audit->log('charge.recorded', $entry, [
                'document_number' => $entry->document_number,
                'type' => $entry->entry_type->value,
                'amount' => (float) $entry->amount,
            ], $request->user());

            return $entry;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':document recorded on :account.', [
                'document' => $entry->document_number,
                'account' => $waterAccount->account_number,
            ]),
        ]);

        return to_route('admin.charges.print', $entry);
    }

    /**
     * The charge note screen: details plus a link that opens the print sheet.
     */
    public function print(AccountLedgerEntry $accountLedgerEntry): Response
    {
        return Inertia::render('admin/charges/Print', $this->chargeProps($accountLedgerEntry));
    }

    /**
     * The bare 80mm print sheet: opened in a new tab, no layout,
     * auto-triggers the print dialog — close the tab when done.
     */
    public function printSheet(AccountLedgerEntry $accountLedgerEntry): Response
    {
        return Inertia::render('print/ChargeNote', $this->chargeProps($accountLedgerEntry));
    }

    /**
     * @return array{charge: array<string, mixed>, account: array<string, mixed>, org: array{name: string}}
     */
    private function chargeProps(AccountLedgerEntry $accountLedgerEntry): array
    {
        abort_unless(in_array($accountLedgerEntry->entry_type, [
            AccountLedgerEntryType::Charge,
            AccountLedgerEntryType::Penalty,
            AccountLedgerEntryType::Adjustment,
        ], true), 404);

        $accountLedgerEntry->load(['waterAccount.owner', 'recorder:id,first_name,last_name']);

        return [
            'charge' => [
                'id' => $accountLedgerEntry->id,
                'document_number' => $accountLedgerEntry->document_number,
                'type' => $accountLedgerEntry->entry_type->value,
                'amount' => (float) $accountLedgerEntry->amount,
                'running_balance' => (float) $accountLedgerEntry->running_balance,
                'description' => $accountLedgerEntry->description,
                'date' => $accountLedgerEntry->entry_date->format('d M Y'),
                'recorded_by' => $accountLedgerEntry->recorder?->name,
            ],
            'account' => [
                'id' => $accountLedgerEntry->waterAccount->id,
                'owner_name' => $accountLedgerEntry->waterAccount->owner->name,
                'account_number' => $accountLedgerEntry->waterAccount->account_number,
            ],
            'org' => [
                'name' => config('app.name'),
            ],
        ];
    }
}
