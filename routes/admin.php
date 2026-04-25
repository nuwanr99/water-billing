<?php

use App\Http\Controllers\Admin\BillController;
use App\Http\Controllers\Admin\BillingCategoryController;
use App\Http\Controllers\Admin\ChargeController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemLedgerAccountController;
use App\Http\Controllers\Admin\SystemLedgerEntryController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WaterAccountController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified', 'can:admin'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('can:users.view')->name('index');
        Route::get('/create', [UserController::class, 'create'])->middleware('can:users.create')->name('create');
        Route::post('/', [UserController::class, 'store'])->middleware('can:users.create')->name('store');
        Route::get('/{user}', [UserController::class, 'edit'])->middleware('can:users.edit')->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->middleware('can:users.edit')->name('update');
        Route::put('/{user}/password', [UserController::class, 'updatePassword'])->middleware('can:users.edit')->name('password.update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('destroy');
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('can:roles.view')->name('index');
        Route::get('/create', [RoleController::class, 'create'])->middleware('can:roles.create')->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware('can:roles.create')->name('store');
        Route::get('/{role}', [RoleController::class, 'edit'])->middleware('can:roles.edit')->name('edit');
        Route::put('/{role}', [RoleController::class, 'update'])->middleware('can:roles.edit')->name('update');
        Route::put('/{role}/permissions', [RoleController::class, 'updatePermissions'])->middleware('can:roles.edit')->name('permissions.update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('destroy');
    });

    Route::prefix('water-accounts')->name('water-accounts.')->group(function () {
        Route::get('/', [WaterAccountController::class, 'index'])->middleware('can:water-accounts.view')->name('index');
        Route::get('/create', [WaterAccountController::class, 'create'])->middleware('can:water-accounts.create')->name('create');
        Route::get('/owners', [WaterAccountController::class, 'owners'])->middleware('can:water-accounts.view')->name('owners');
        Route::post('/', [WaterAccountController::class, 'store'])->middleware('can:water-accounts.create')->name('store');
        Route::get('/{waterAccount}/statement', [WaterAccountController::class, 'statement'])->middleware('can:ledger.view')->name('statement');
        Route::get('/{waterAccount}/charges/create', [ChargeController::class, 'create'])->middleware('can:ledger.record-charge')->name('charges.create');
        Route::post('/{waterAccount}/charges', [ChargeController::class, 'store'])->middleware('can:ledger.record-charge')->name('charges.store');
        Route::get('/{waterAccount}', [WaterAccountController::class, 'edit'])->middleware('can:water-accounts.edit')->name('edit');
        Route::put('/{waterAccount}', [WaterAccountController::class, 'update'])->middleware('can:water-accounts.edit')->name('update');
    });

    Route::prefix('bills')->name('bills.')->group(function () {
        Route::get('/', [BillController::class, 'index'])->middleware('can:bills.view')->name('index');
        Route::get('/{bill}', [BillController::class, 'show'])->middleware('can:bills.view')->name('show');
        Route::post('/{bill}/reissue', [BillController::class, 'reissue'])->middleware('can:bills.reissue')->name('reissue');
    });

    Route::get('/charges/{accountLedgerEntry}/print', [ChargeController::class, 'print'])->middleware('can:ledger.view')->name('charges.print');
    Route::get('/charges/{accountLedgerEntry}/print/sheet', [ChargeController::class, 'printSheet'])->middleware('can:ledger.view')->name('charges.print.sheet');

    Route::prefix('payments')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->middleware('can:payments.view-all')->name('index');
        Route::get('/collect', [PaymentController::class, 'collect'])->middleware('can:payments.record-manual')->name('collect');
        Route::get('/collect/{waterAccount}', [PaymentController::class, 'show'])->middleware('can:payments.record-manual')->name('collect.show');
        Route::post('/collect/{waterAccount}', [PaymentController::class, 'store'])->middleware('can:payments.record-manual')->name('collect.store');
        Route::get('/receipt/{payment}', [PaymentController::class, 'receipt'])->name('receipt');
        Route::get('/receipt/{payment}/print', [PaymentController::class, 'receiptPrint'])->name('receipt.print');
        Route::get('/{payment}/attachment', [PaymentController::class, 'attachment'])->name('attachment');
    });

    Route::prefix('system-ledger')->name('system-ledger.')->group(function () {
        Route::get('/', [SystemLedgerEntryController::class, 'index'])->middleware('can:system-ledger.view')->name('index');
        Route::get('/create', [SystemLedgerEntryController::class, 'create'])->middleware('can:system-ledger.manage')->name('create');
        Route::post('/', [SystemLedgerEntryController::class, 'store'])->middleware('can:system-ledger.manage')->name('store');
        Route::post('/transfer', [SystemLedgerEntryController::class, 'storeTransfer'])->middleware('can:system-ledger.manage')->name('transfer.store');
        Route::get('/{systemLedgerEntry}', [SystemLedgerEntryController::class, 'show'])->middleware('can:system-ledger.view')->name('show');
    });

    Route::prefix('ledger-accounts')->name('ledger-accounts.')->group(function () {
        Route::get('/', [SystemLedgerAccountController::class, 'index'])->middleware('can:system-ledger.view')->name('index');
        Route::get('/create', [SystemLedgerAccountController::class, 'create'])->middleware('can:system-ledger.manage')->name('create');
        Route::post('/', [SystemLedgerAccountController::class, 'store'])->middleware('can:system-ledger.manage')->name('store');
        Route::get('/{systemLedgerAccount}', [SystemLedgerAccountController::class, 'show'])->middleware('can:system-ledger.view')->name('show');
        Route::get('/{systemLedgerAccount}/edit', [SystemLedgerAccountController::class, 'edit'])->middleware('can:system-ledger.manage')->name('edit');
        Route::put('/{systemLedgerAccount}', [SystemLedgerAccountController::class, 'update'])->middleware('can:system-ledger.manage')->name('update');
        Route::delete('/{systemLedgerAccount}', [SystemLedgerAccountController::class, 'destroy'])->middleware('can:system-ledger.manage')->name('destroy');
    });

    Route::prefix('billing-categories')->name('billing-categories.')->group(function () {
        Route::get('/', [BillingCategoryController::class, 'index'])->middleware('can:tariffs.view')->name('index');
        Route::get('/create', [BillingCategoryController::class, 'create'])->middleware('can:tariffs.manage')->name('create');
        Route::post('/', [BillingCategoryController::class, 'store'])->middleware('can:tariffs.manage')->name('store');
        Route::get('/{billingCategory}', [BillingCategoryController::class, 'edit'])->middleware('can:tariffs.manage')->name('edit');
        Route::put('/{billingCategory}', [BillingCategoryController::class, 'update'])->middleware('can:tariffs.manage')->name('update');
        Route::delete('/{billingCategory}', [BillingCategoryController::class, 'destroy'])->middleware('can:tariffs.manage')->name('destroy');
    });

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:permissions.view')->name('permissions.index');

    Route::prefix('complaints')->name('complaints.')->group(function () {
        Route::get('/', [ComplaintController::class, 'index'])->middleware('can:complaints.view-all')->name('index');
        Route::get('/handlers', [ComplaintController::class, 'handlers'])->middleware('can:complaints.view-all')->name('handlers');
        Route::get('/{complaint}', [ComplaintController::class, 'show'])->middleware('can:complaints.view-all')->name('show');
        Route::post('/{complaint}/assign', [ComplaintController::class, 'assign'])->middleware('can:complaints.manage')->name('assign');
        Route::post('/{complaint}/reply', [ComplaintController::class, 'reply'])->middleware('can:complaints.manage')->name('reply');
        Route::post('/{complaint}/close', [ComplaintController::class, 'close'])->middleware('can:complaints.manage')->name('close');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::redirect('/', '/admin/settings/notifications')->name('index');
        Route::get('/users', [SettingController::class, 'users'])->middleware('can:settings.manage')->name('users');
        Route::get('/notifications', [SettingController::class, 'notifications'])->middleware('can:settings.manage')->name('notifications.edit');
        Route::put('/notifications', [SettingController::class, 'updateNotifications'])->middleware('can:settings.manage')->name('notifications.update');
    });
});
