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
Route::middleware('auth')->prefix('admin')->group(function () { Route::get('/storage', [\App\Http\Controllers\Admin\StorageController::class, 'index'])->name('admin.storage.index'); Route::post('/storage', [\App\Http\Controllers\Admin\StorageController::class, 'update'])->name('admin.storage.update'); Route::post('/storage/check', [\App\Http\Controllers\Admin\StorageController::class, 'check'])->name('admin.storage.check'); });

// Appearance settings (admin only; checked inside the controller)
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/appearance', [\App\Http\Controllers\Admin\AppearanceController::class, 'index'])->name('admin.appearance.index');
    Route::post('/appearance', [\App\Http\Controllers\Admin\AppearanceController::class, 'update'])->name('admin.appearance.update');
    Route::post('/appearance/reset', [\App\Http\Controllers\Admin\AppearanceController::class, 'reset'])->name('admin.appearance.reset');
});
// ---- Admin-managed pages ----
\Illuminate\Support\Facades\Route::get('/p/{slug}', [\App\Http\Controllers\PageController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('page.show');

\Illuminate\Support\Facades\Route::prefix('admin/pages')->name('admin.pages.')->group(function () {
    $c = \App\Http\Controllers\Admin\PagesController::class;
    \Illuminate\Support\Facades\Route::get('/', [$c, 'index'])->name('index');
    \Illuminate\Support\Facades\Route::get('/create', [$c, 'create'])->name('create');
    \Illuminate\Support\Facades\Route::post('/', [$c, 'store'])->name('store');
    \Illuminate\Support\Facades\Route::get('/{page}/edit', [$c, 'edit'])->name('edit');
    \Illuminate\Support\Facades\Route::put('/{page}', [$c, 'update'])->name('update');
    \Illuminate\Support\Facades\Route::delete('/{page}', [$c, 'destroy'])->name('destroy');
});


Route::get('/admin/areas/{area}/children', \App\Http\Controllers\Admin\AreaChildrenController::class)->name('admin.areas.children');


Route::get('/areas/locate', \App\Http\Controllers\AreaLocateController::class)->middleware('throttle:60,1')->name('areas.locate');
Route::get('/areas/{area}/children', \App\Http\Controllers\AreaChildrenController::class)->name('areas.children');

// ---- Area volunteer moderators (admin only; checked inside the controller) ----
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin/moderators')->name('admin.moderators.')->group(function () {
    $c = \App\Http\Controllers\Admin\ModeratorsController::class;
    \Illuminate\Support\Facades\Route::get('/', [$c, 'index'])->name('index');
    \Illuminate\Support\Facades\Route::post('/', [$c, 'store'])->name('store');
    \Illuminate\Support\Facades\Route::post('/{grant}/revoke', [$c, 'revoke'])->name('revoke');
});
