<?php

use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\IssueListController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/issues', [IssueListController::class, 'index'])->name('issues.index');
Route::get('/issues/{issue:public_id}', [IssueController::class, 'show'])->name('issues.show');

// Report flow (login required)
Route::middleware('auth')->group(function () {
    Route::get('/report', [ReportController::class, 'create'])->name('report.create');
    Route::post('/report', [ReportController::class, 'store'])->middleware('throttle:20,1')->name('report.store');
    Route::get('/report/success', [ReportController::class, 'success'])->name('report.success');

    // Moderation (moderator/admin only; checked inside the controller)
    Route::prefix('admin')->group(function () {
        Route::get('/moderation', [ModerationController::class, 'index'])->name('admin.moderation.index');
        Route::post('/moderation/{report}/approve', [ModerationController::class, 'approve'])->name('admin.moderation.approve');
        Route::post('/moderation/{report}/hide', [ModerationController::class, 'hide'])->name('admin.moderation.hide');
        Route::get('/evidence/{evidence}', [ModerationController::class, 'photo'])->name('admin.evidence.photo');
    });
});

// Login with phone + OTP
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'sendCode'])->middleware('throttle:10,1');
    Route::get('/login/verify', [AuthController::class, 'showVerify'])->name('login.verify');
    Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1')->name('login.verify.submit');
    Route::post('/login/resend', [AuthController::class, 'resend'])->middleware('throttle:5,1')->name('login.resend');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['bn', 'en'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('lang');