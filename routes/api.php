<?php

use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\FundDonateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/issues/nearby', [IssueController::class, 'nearby']);
});

// UddoktaPay IPN/webhook (no auth, no CSRF; state is always verified server-side).
Route::post('/fund/uddoktapay/webhook', [FundDonateController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('fund.pay.webhook');
