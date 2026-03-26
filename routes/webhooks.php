<?php

use App\Http\Controllers\PayHereWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes
|--------------------------------------------------------------------------
|
| Stateless server-to-server callbacks from payment gateways. These run
| under the minimal "webhooks" middleware group — no sessions, cookies,
| or CSRF — because callers are machines that authenticate through
| payload signatures (PayHere: md5sig recomputation), not tokens.
|
*/

Route::post('payhere/notify', PayHereWebhookController::class)->name('payhere.notify');
