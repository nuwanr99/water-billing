<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\OtpLoginController;
use App\Http\Controllers\BillController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberPaymentController;
use App\Http\Controllers\MeterReadingController;
use App\Http\Controllers\My\BillController as MyBillController;
use App\Http\Controllers\My\ComplaintController as MyComplaintController;
use App\Http\Controllers\My\JobController as MyJobController;
use App\Http\Controllers\My\PaymentController as MyPaymentController;
use App\Http\Controllers\PublicPaymentController;
use App\Http\Controllers\WaterAccountController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['guest', 'throttle:otp'])->prefix('login/otp')->name('login.otp.')->group(function () {
    Route::post('request', [OtpLoginController::class, 'request'])->name('request');
    Route::post('verify', [OtpLoginController::class, 'verify'])->name('verify');
});

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
        Route::get('{bill}', [BillController::class, 'show'])->name('show')->middleware('can:view,bill');
        Route::get('{bill}/pdf', [BillController::class, 'pdf'])->name('pdf')->middleware('can:view,bill');
    });

    Route::prefix('my/pay')->name('my.pay.')->group(function () {
        Route::get('{waterAccount}', [MemberPaymentController::class, 'show'])->name('show');
        Route::post('{waterAccount}', [MemberPaymentController::class, 'checkout'])->name('checkout');
    });

    Route::prefix('my/bills')->name('my.bills.')->group(function () {
        Route::get('', [MyBillController::class, 'index'])->middleware('can:bills.view-own')->name('index');
    });

    Route::prefix('my/payments')->name('my.payments.')->group(function () {
        Route::get('', [MyPaymentController::class, 'index'])->middleware('can:payments.view-own')->name('index');
        Route::get('{payment}/receipt', [MyPaymentController::class, 'receipt'])->middleware('can:payments.view-own')->name('receipt');
    });

    Route::prefix('my/complaints')->name('my.complaints.')->group(function () {
        Route::get('', [MyComplaintController::class, 'index'])->middleware('can:complaints.view-own')->name('index');
        Route::get('create', [MyComplaintController::class, 'create'])->middleware('can:complaints.submit')->name('create');
        Route::post('', [MyComplaintController::class, 'store'])->middleware('can:complaints.submit')->name('store');
        Route::get('{complaint}', [MyComplaintController::class, 'show'])->name('show');
        Route::post('{complaint}/reply', [MyComplaintController::class, 'reply'])->name('reply');
        Route::post('{complaint}/close', [MyComplaintController::class, 'close'])->name('close');
    });

    Route::prefix('my-jobs')->name('my-jobs.')->group(function () {
        Route::get('', [MyJobController::class, 'index'])->middleware('can:maintenance-jobs.view-assigned')->name('index');
        Route::get('{maintenanceJob}', [MyJobController::class, 'show'])->name('show');
        Route::post('{maintenanceJob}/updates', [MyJobController::class, 'postUpdate'])->name('updates');
        Route::post('{maintenanceJob}/status', [MyJobController::class, 'updateStatus'])->middleware('can:maintenance-jobs.update-status')->name('status');
    });

    Route::get('attachments/{attachment}', AttachmentController::class)->name('attachments.download');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
