<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\IssueListController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Public issue list with filters.
Route::get('/issues', [IssueListController::class, 'index'])
    ->name('issues.index');

// Public issue page. Uses the public ULID, not the internal number.
Route::get('/issues/{issue:public_id}', [IssueController::class, 'show'])
    ->name('issues.show');

// Login with phone + OTP
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'sendCode'])->middleware('throttle:10,1');
    Route::get('/login/verify', [AuthController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1')->name('login.verify.submit');
    Route::post('/login/resend', [AuthController::class, 'resend'])->middleware('throttle:5,1')->name('login.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Language switch: /lang/bn or /lang/en
Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['bn', 'en'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('lang');