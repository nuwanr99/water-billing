<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
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
        Route::get('/{waterAccount}', [WaterAccountController::class, 'edit'])->middleware('can:water-accounts.edit')->name('edit');
        Route::put('/{waterAccount}', [WaterAccountController::class, 'update'])->middleware('can:water-accounts.edit')->name('update');
    });

    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('can:permissions.view')->name('permissions.index');
});
