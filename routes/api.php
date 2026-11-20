<?php

use App\Http\Controllers\Api\IssueController;
use App\Http\Controllers\FundDonateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/issues/nearby', [IssueController::class, 'nearby']);
});

// Public read-only API (app + third parties). Keys/limits: Admin → API অ্যাক্সেস.
Route::prefix('v1')->middleware(\App\Http\Middleware\ApiAccess::class)->group(function () {
    $c = \App\Http\Controllers\Api\PublicApiController::class;
    Route::get('/issues', [$c, 'issues'])->name('api.issues');
    Route::get('/issues/{issue:public_id}', [$c, 'show'])->name('api.issues.show');
    Route::get('/categories', [$c, 'categories'])->name('api.categories');
    Route::get('/areas', [$c, 'areas'])->name('api.areas');
    Route::get('/stats', [$c, 'stats'])->name('api.stats');
});

// UddoktaPay IPN/webhook (no auth, no CSRF; state is always verified server-side).
Route::post('/fund/sslcommerz/ipn', [FundDonateController::class, 'sslIpn'])
    ->middleware('throttle:120,1')
    ->name('fund.pay.ssl.ipn');

Route::post('/fund/uddoktapay/webhook', [FundDonateController::class, 'webhook'])
    ->middleware('throttle:120,1')
    ->name('fund.pay.webhook');
