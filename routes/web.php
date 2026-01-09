<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WaterAccountController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('water-accounts', [WaterAccountController::class, 'index'])->name('water-accounts.index');
    Route::post('water-accounts/{waterAccount}/switch', [WaterAccountController::class, 'switchTo'])->name('water-accounts.switch');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
