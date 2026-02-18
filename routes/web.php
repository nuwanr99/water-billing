<?php

use App\Http\Controllers\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\WaterAccountController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
