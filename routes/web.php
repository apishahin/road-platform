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
        Route::post('/evidence/{evidence}/hide', [ModerationController::class, 'hideEvidence'])->name('admin.evidence.hide');
        Route::post('/evidence/{evidence}/blur', [ModerationController::class, 'blurEvidence'])->name('admin.evidence.blur');
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

// ---- Volunteer applications ----
\Illuminate\Support\Facades\Route::middleware('auth')->group(function () {
    \Illuminate\Support\Facades\Route::get('/volunteer/apply', [\App\Http\Controllers\VolunteerApplicationController::class, 'create'])->name('volunteer.apply');
    \Illuminate\Support\Facades\Route::post('/volunteer/apply', [\App\Http\Controllers\VolunteerApplicationController::class, 'store'])->middleware('throttle:5,1')->name('volunteer.apply.store');
});
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin/volunteer-applications')->name('admin.applications.')->group(function () {
    $c = \App\Http\Controllers\Admin\VolunteerApplicationsController::class;
    \Illuminate\Support\Facades\Route::get('/', [$c, 'index'])->name('index');
    \Illuminate\Support\Facades\Route::post('/{application}/approve', [$c, 'approve'])->name('approve');
    \Illuminate\Support\Facades\Route::post('/{application}/reject', [$c, 'reject'])->name('reject');
});

// Published issues: unpublish, publish again, edit (admin / moderator)
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/issues', [\App\Http\Controllers\Admin\IssueAdminController::class, 'index'])->name('admin.issues.index');
    Route::get('/issues/{issue:id}/edit', [\App\Http\Controllers\Admin\IssueAdminController::class, 'edit'])->name('admin.issues.edit');
    Route::post('/issues/{issue:id}/update', [\App\Http\Controllers\Admin\IssueAdminController::class, 'update'])->name('admin.issues.update');
    Route::post('/issues/{issue:id}/unpublish', [\App\Http\Controllers\Admin\IssueAdminController::class, 'unpublish'])->name('admin.issues.unpublish');
    Route::post('/issues/{issue:id}/republish', [\App\Http\Controllers\Admin\IssueAdminController::class, 'republish'])->name('admin.issues.republish');
});

// Authority contacts: add, approve, CSV import (admin / moderator)
Route::middleware('auth')->prefix('admin')->group(function () {
    $c = \App\Http\Controllers\Admin\AuthorityContactController::class;
    Route::get('/authority-contacts', [$c, 'index'])->name('admin.contacts.index');
    Route::post('/authority-contacts', [$c, 'store'])->name('admin.contacts.store');
    Route::get('/authority-contacts/area-search', [$c, 'areaSearch'])->name('admin.contacts.areas');
    Route::get('/authority-contacts/template.csv', [$c, 'template'])->name('admin.contacts.template');
    Route::get('/authority-contacts/areas.csv', [$c, 'areasCsv'])->name('admin.contacts.areascsv');
    Route::post('/authority-contacts/import', [$c, 'import'])->name('admin.contacts.import');
    Route::post('/authority-contacts/{contact:id}/approve', [$c, 'approve'])->name('admin.contacts.approve');
    Route::post('/authority-contacts/{contact:id}/disable', [$c, 'disable'])->name('admin.contacts.disable');
    Route::post('/authority-contacts/{contact:id}/enable', [$c, 'enable'])->name('admin.contacts.enable');
});

\Illuminate\Support\Facades\Route::prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('users', [\App\Http\Controllers\Admin\UserAdminController::class, 'index'])->name('users.index');
    \Illuminate\Support\Facades\Route::post('users/{id}/role', [\App\Http\Controllers\Admin\UserAdminController::class, 'setRole'])->whereNumber('id')->name('users.role');
});

\Illuminate\Support\Facades\Route::prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('users-areas', [\App\Http\Controllers\Admin\UserAdminController::class, 'areas'])->name('users.areas');
});

\Illuminate\Support\Facades\Route::prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('issues/{issue}/notify', [\App\Http\Controllers\Admin\IssueNotifyController::class, 'show'])->whereNumber('issue')->name('issues.notify');
    \Illuminate\Support\Facades\Route::post('issues/{issue}/notify/email', [\App\Http\Controllers\Admin\IssueNotifyController::class, 'sendEmail'])->whereNumber('issue')->name('issues.notify.send');
    \Illuminate\Support\Facades\Route::post('issues/{issue}/notify/whatsapp/{contact}', [\App\Http\Controllers\Admin\IssueNotifyController::class, 'whatsapp'])->whereNumber('issue')->whereNumber('contact')->name('issues.notify.whatsapp');
});

\Illuminate\Support\Facades\Route::get('admin/issues-areas', \App\Http\Controllers\Admin\IssueAreaSearchController::class)->name('admin.issues.areas');

\Illuminate\Support\Facades\Route::prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('mail-settings', [\App\Http\Controllers\Admin\MailSettingsController::class, 'show'])->name('mail.index');
    \Illuminate\Support\Facades\Route::post('mail-settings', [\App\Http\Controllers\Admin\MailSettingsController::class, 'save'])->name('mail.save');
    \Illuminate\Support\Facades\Route::post('mail-settings/test', [\App\Http\Controllers\Admin\MailSettingsController::class, 'test'])->name('mail.test');
});

\Illuminate\Support\Facades\Route::prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('login-settings', [\App\Http\Controllers\Admin\LoginSettingsController::class, 'show'])->name('loginsettings.index');
    \Illuminate\Support\Facades\Route::post('login-settings', [\App\Http\Controllers\Admin\LoginSettingsController::class, 'save'])->name('loginsettings.save');
});

\Illuminate\Support\Facades\Route::middleware('guest')->group(function () {
    \Illuminate\Support\Facades\Route::post('/login/password', [\App\Http\Controllers\AuthController::class, 'passwordLogin'])->middleware('throttle:10,1')->name('login.password');
    \Illuminate\Support\Facades\Route::post('/login/register', [\App\Http\Controllers\AuthController::class, 'register'])->middleware('throttle:5,1')->name('login.register');
});

Route::middleware('auth')->group(function () {
    Route::get('/account/password', [\App\Http\Controllers\AccountPasswordController::class, 'show'])->name('account.password');
    Route::post('/account/password', [\App\Http\Controllers\AccountPasswordController::class, 'save'])->middleware('throttle:10,1')->name('account.password.save');
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/sms-gateways', [\App\Http\Controllers\Admin\SmsGatewaysController::class, 'index'])->name('smsgateways.index');
    Route::post('/sms-gateways', [\App\Http\Controllers\Admin\SmsGatewaysController::class, 'save'])->name('smsgateways.save');
    Route::post('/sms-gateways/{id}/toggle', [\App\Http\Controllers\Admin\SmsGatewaysController::class, 'toggle'])->whereNumber('id')->name('smsgateways.toggle');
    Route::post('/sms-gateways/{id}/delete', [\App\Http\Controllers\Admin\SmsGatewaysController::class, 'delete'])->whereNumber('id')->name('smsgateways.delete');
    Route::post('/sms-gateways/{id}/test', [\App\Http\Controllers\Admin\SmsGatewaysController::class, 'test'])->whereNumber('id')->middleware('throttle:10,1')->name('smsgateways.test');
});

Route::middleware('guest')->group(function () {
    Route::post('/login/firebase/verify', [\App\Http\Controllers\FirebaseLoginController::class, 'verify'])->middleware('throttle:10,1')->name('login.firebase.verify');
});

\Illuminate\Support\Facades\Route::post('/issues/{publicId}/observe', [\App\Http\Controllers\IssueObservationController::class, 'store'])->middleware(['auth', 'throttle:20,1'])->name('issues.observe');

\Illuminate\Support\Facades\Route::post('/issues/{publicId}/verify', [\App\Http\Controllers\IssueVerifyController::class, 'store'])->middleware(['auth', 'throttle:20,1'])->name('issues.verify');
\Illuminate\Support\Facades\Route::get('/verify-queue', [\App\Http\Controllers\IssueVerifyController::class, 'queue'])->middleware('auth')->name('verify.queue');

// ---- Update photos (citizen updates / on-site checks) ----
\Illuminate\Support\Facades\Route::middleware('auth')->group(function () {
    \Illuminate\Support\Facades\Route::get('/photo-review', [\App\Http\Controllers\PhotoReviewController::class, 'index'])->name('photoreview.index');
    \Illuminate\Support\Facades\Route::get('/photo-review/{evidence}/image', [\App\Http\Controllers\PhotoReviewController::class, 'image'])->name('photoreview.image');
    \Illuminate\Support\Facades\Route::post('/photo-review/{evidence}/approve', [\App\Http\Controllers\PhotoReviewController::class, 'approve'])->name('photoreview.approve');
    \Illuminate\Support\Facades\Route::post('/photo-review/{evidence}/hide', [\App\Http\Controllers\PhotoReviewController::class, 'hide'])->name('photoreview.hide');
    \Illuminate\Support\Facades\Route::post('/photo-review/switch', [\App\Http\Controllers\PhotoReviewController::class, 'toggle'])->name('photoreview.toggle');
});

// ---- Update photos: cover (blur / black) a pending photo ----
\Illuminate\Support\Facades\Route::middleware('auth')->group(function () {
    \Illuminate\Support\Facades\Route::post('/photo-review/{evidence}/blur', [\App\Http\Controllers\PhotoReviewController::class, 'blur'])->name('photoreview.blur');
});

// ---- Funding / donations ----
\Illuminate\Support\Facades\Route::get('/fund', [\App\Http\Controllers\FundController::class, 'show'])->name('fund.show');
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin/funding')->name('admin.funding.')->group(function () {
    \Illuminate\Support\Facades\Route::get('/', [\App\Http\Controllers\Admin\FundingController::class, 'index'])->name('index');
    \Illuminate\Support\Facades\Route::post('/save', [\App\Http\Controllers\Admin\FundingController::class, 'save'])->name('save');
    \Illuminate\Support\Facades\Route::post('/manual', [\App\Http\Controllers\Admin\FundingController::class, 'manual'])->name('manual');
    \Illuminate\Support\Facades\Route::post('/donations/{donation}/void', [\App\Http\Controllers\Admin\FundingController::class, 'void'])->name('void');
});

// ---- Funding: expenses and vouchers ----
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin/funding')->name('admin.funding.')->group(function () {
    \Illuminate\Support\Facades\Route::post('/expenses', [\App\Http\Controllers\Admin\FundExpenseController::class, 'store'])->name('expense.store');
    \Illuminate\Support\Facades\Route::post('/expenses/{expense}/delete', [\App\Http\Controllers\Admin\FundExpenseController::class, 'destroy'])->name('expense.delete');
});
