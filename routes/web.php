<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberPaymentController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\WaterAccountController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::prefix('pay')->name('pay.')->group(function () {
    Route::get('', [PublicPaymentController::class, 'show'])->name('show');
    Route::post('lookup', [PublicPaymentController::class, 'lookup'])->name('lookup');
    Route::post('checkout', [PublicPaymentController::class, 'checkout'])->name('checkout');
    Route::get('result/{payment:public_token}', [PublicPaymentController::class, 'result'])->name('result');
    Route::get('receipt/{payment:public_token}', [PublicPaymentController::class, 'receiptPdf'])->name('receipt-pdf');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('water-accounts', [WaterAccountController::class, 'index'])->name('water-accounts.index');
    Route::post('water-accounts/{waterAccount}/switch', [WaterAccountController::class, 'switchTo'])->name('water-accounts.switch');

    Route::prefix('meter-readings')->name('meter-readings.')->group(function () {
        Route::get('', [MeterReadingController::class, 'index'])->name('index')->middleware('can:readings.view');
        Route::get('{waterAccount}/create', [MeterReadingController::class, 'create'])->name('create')->middleware('can:readings.create');
        Route::post('{waterAccount}', [MeterReadingController::class, 'store'])->name('store')->middleware('can:readings.create');
        Route::get('{waterAccount}/history', [MeterReadingController::class, 'history'])->name('history')->middleware('can:readings.view');
        Route::get('reading/{meterReading}/edit', [MeterReadingController::class, 'edit'])->name('edit')->middleware('can:readings.edit');
        Route::put('reading/{meterReading}', [MeterReadingController::class, 'update'])->name('update')->middleware('can:readings.edit');
    });

    Route::prefix('bills')->name('bills.')->group(function () {
        Route::get('preview/{meterReading}', [BillController::class, 'preview'])->name('preview')->middleware('can:bills.generate');
        Route::post('preview/{meterReading}', [BillController::class, 'store'])->name('store')->middleware('can:bills.generate');
        Route::get('{bill}', [BillController::class, 'show'])->name('show')->middleware('can:bills.view');
    });

    Route::prefix('my/pay')->name('my.pay.')->group(function () {
        Route::get('{waterAccount}', [MemberPaymentController::class, 'show'])->name('show');
        Route::post('{waterAccount}', [MemberPaymentController::class, 'checkout'])->name('checkout');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
