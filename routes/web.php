<?php

use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\IssueListController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::view('/offline', 'offline')->name('offline');

Route::get('/issues', [IssueListController::class, 'index'])->name('issues.index');
Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'sitemap'])->name('sitemap');

\Illuminate\Support\Facades\Route::middleware('auth')->get('/admin/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('admin.analytics.index');
\Illuminate\Support\Facades\Route::middleware('auth')->get('/admin/analytics.csv', [\App\Http\Controllers\Admin\AnalyticsController::class, 'csv'])->name('admin.analytics.csv');
Route::get('/robots.txt', [\App\Http\Controllers\SitemapController::class, 'robots'])->name('robots');

// ads.txt — the ad networks' verification file (served only when configured)
Route::get('/ads.txt', function () {
    $c = \App\Support\AdSettings::adsTxt();
    abort_if($c === '', 404);

    return response($c, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('ads.txt');

\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('/ads', [\App\Http\Controllers\Admin\AdSettingsController::class, 'index'])->name('ads.index');
    \Illuminate\Support\Facades\Route::post('/ads', [\App\Http\Controllers\Admin\AdSettingsController::class, 'save'])->name('ads.save');
});
Route::get('/issues/{issue:public_id}', [IssueController::class, 'show'])->name('issues.show');
Route::post('/issues/{issue:public_id}/follow', [\App\Http\Controllers\IssueFollowController::class, 'toggle'])->middleware('auth')->name('issues.follow');
Route::get('/notifications', [\App\Http\Controllers\NotificationsController::class, 'index'])->middleware('auth')->name('notifications.index');
Route::get('/impact', [\App\Http\Controllers\ImpactController::class, 'index'])->name('impact');
Route::view('/developers', 'developers')->name('developers');
Route::get('/leaderboard', [\App\Http\Controllers\LeaderboardController::class, 'index'])->name('leaderboard');
Route::post('/push/token', [\App\Http\Controllers\PushTokenController::class, 'store'])->middleware(['auth', 'throttle:20,1'])->name('push.token');
Route::delete('/push/token', [\App\Http\Controllers\PushTokenController::class, 'destroy'])->middleware('auth')->name('push.token.delete');
// Service worker for web push (must be served from the root with config baked in)
Route::get('/firebase-messaging-sw.js', function () {
    $cfg = \App\Support\PushSettings::webConfig();
    $js = "importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');\n"
        . "importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');\n"
        . 'firebase.initializeApp(' . json_encode($cfg) . ');' . "\n"
        . "const m = firebase.messaging();\n"
        . "m.onBackgroundMessage((p) => { const n = (p && p.notification) || {}; self.registration.showNotification(n.title || '', { body: n.body || '', data: { url: (p.data && p.data.url) || '/' } }); });\n"
        . "self.addEventListener('notificationclick', (e) => { e.notification.close(); e.waitUntil(clients.openWindow((e.notification.data && e.notification.data.url) || '/')); });\n";

    return response($js, 200, ['Content-Type' => 'application/javascript; charset=UTF-8', 'Cache-Control' => 'no-store']);
})->name('push.sw');

// Report flow (login required)
Route::middleware('auth')->group(function () {
    // Verify (or set + verify) the mobile number of a logged-in account.
    Route::get('/account/phone', [\App\Http\Controllers\PhoneVerificationController::class, 'show'])->name('account.phone');
    Route::post('/account/phone/send', [\App\Http\Controllers\PhoneVerificationController::class, 'send'])->middleware('throttle:10,1')->name('account.phone.send');
    Route::post('/account/phone/verify', [\App\Http\Controllers\PhoneVerificationController::class, 'verify'])->middleware('throttle:10,1')->name('account.phone.verify');
    Route::post('/account/phone/firebase', [\App\Http\Controllers\PhoneVerificationController::class, 'firebase'])->middleware('throttle:10,1')->name('account.phone.firebase');
    Route::post('/account/phone/dismiss', [\App\Http\Controllers\PhoneVerificationController::class, 'dismiss'])->name('account.phone.dismiss');

    // Self-service account deletion (citizen, OTP-confirmed).
    Route::get('/account/delete', [\App\Http\Controllers\AccountDeletionController::class, 'show'])->name('account.delete');
    Route::post('/account/delete/send', [\App\Http\Controllers\AccountDeletionController::class, 'send'])->middleware('throttle:5,1')->name('account.delete.send');
    Route::post('/account/delete', [\App\Http\Controllers\AccountDeletionController::class, 'destroy'])->middleware('throttle:5,1')->name('account.delete.confirm');

    Route::get('/report', [ReportController::class, 'create'])->middleware('phone.verified')->name('report.create');
    Route::post('/report', [ReportController::class, 'store'])->middleware(['phone.verified', 'throttle:20,1'])->name('report.store');
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
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/storage', [\App\Http\Controllers\Admin\StorageController::class, 'index'])->name('admin.storage.index');
    Route::post('/storage', [\App\Http\Controllers\Admin\StorageController::class, 'update'])->name('admin.storage.update');
    Route::post('/storage/check', [\App\Http\Controllers\Admin\StorageController::class, 'check'])->name('admin.storage.check');
    Route::post('/storage/r2', [\App\Http\Controllers\Admin\StorageController::class, 'saveR2'])->name('admin.storage.r2');
    Route::post('/storage/keep-originals', [\App\Http\Controllers\Admin\StorageController::class, 'keepOriginals'])->name('admin.storage.keep-originals');
    Route::post('/storage/encode', [\App\Http\Controllers\Admin\StorageController::class, 'saveEncode'])->name('admin.storage.encode');
});

Route::middleware('auth')->prefix('admin')->group(function () {
    $c = \App\Http\Controllers\Admin\ApiAccessController::class;
    Route::get('/api-access', [$c, 'index'])->name('admin.api.index');
    Route::post('/api-access/toggle', [$c, 'toggle'])->name('admin.api.toggle');
    Route::post('/api-access/keys', [$c, 'createKey'])->name('admin.api.keys.store');
    Route::delete('/api-access/keys/{id}', [$c, 'revoke'])->name('admin.api.keys.destroy');
});

// Appearance settings (admin only; checked inside the controller)
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/appearance', [\App\Http\Controllers\Admin\AppearanceController::class, 'index'])->name('admin.appearance.index');
    Route::post('/appearance', [\App\Http\Controllers\Admin\AppearanceController::class, 'update'])->name('admin.appearance.update');
    Route::post('/appearance/reset', [\App\Http\Controllers\Admin\AppearanceController::class, 'reset'])->name('admin.appearance.reset');
    Route::post('/appearance/pwa-icon', [\App\Http\Controllers\Admin\AppearanceController::class, 'pwaIcon'])->name('admin.appearance.pwa.icon');
    Route::post('/appearance/pwa-icon/remove', [\App\Http\Controllers\Admin\AppearanceController::class, 'pwaIconRemove'])->name('admin.appearance.pwa.icon.remove');
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
    \Illuminate\Support\Facades\Route::get('/volunteer/apply', [\App\Http\Controllers\VolunteerApplicationController::class, 'create'])->middleware('phone.verified')->name('volunteer.apply');
    \Illuminate\Support\Facades\Route::post('/volunteer/apply', [\App\Http\Controllers\VolunteerApplicationController::class, 'store'])->middleware(['phone.verified', 'throttle:5,1'])->name('volunteer.apply.store');
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
    Route::get('/issues-export.csv', [\App\Http\Controllers\Admin\IssueAdminController::class, 'export'])->name('admin.issues.export');
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
    \Illuminate\Support\Facades\Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, '__invoke'])->name('dashboard');
    \Illuminate\Support\Facades\Route::get('users', [\App\Http\Controllers\Admin\UserAdminController::class, 'index'])->name('users.index');
    \Illuminate\Support\Facades\Route::post('users/{id}/role', [\App\Http\Controllers\Admin\UserAdminController::class, 'setRole'])->whereNumber('id')->name('users.role');
    \Illuminate\Support\Facades\Route::post('users/{id}/block', [\App\Http\Controllers\Admin\UserAdminController::class, 'block'])->whereNumber('id')->middleware('throttle:20,1')->name('users.block');
    \Illuminate\Support\Facades\Route::post('users/{id}/unblock', [\App\Http\Controllers\Admin\UserAdminController::class, 'unblock'])->whereNumber('id')->middleware('throttle:20,1')->name('users.unblock');
    \Illuminate\Support\Facades\Route::post('users/{id}/delete', [\App\Http\Controllers\Admin\UserAdminController::class, 'destroy'])->whereNumber('id')->middleware('throttle:20,1')->name('users.delete');
    \Illuminate\Support\Facades\Route::post('blocked-ips/{id}/delete', [\App\Http\Controllers\Admin\UserAdminController::class, 'unblockIp'])->whereNumber('id')->middleware('throttle:20,1')->name('users.unblockip');
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
    // Private fallback: deliberately not linked or rendered on public /login.
    \Illuminate\Support\Facades\Route::get('/admin/login', [\App\Http\Controllers\AuthController::class, 'showStaffLogin'])->name('staff.login');
    \Illuminate\Support\Facades\Route::post('/admin/login', [\App\Http\Controllers\AuthController::class, 'staffPasswordLogin'])->middleware('throttle:10,1')->name('staff.login.submit');
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

    // Google sign-in (Firebase "popup" flow, token verified server-side).
    Route::get('/login/google', [\App\Http\Controllers\GoogleLoginController::class, 'start'])->name('login.google');
    Route::post('/login/google/verify', [\App\Http\Controllers\GoogleLoginController::class, 'verify'])->middleware('throttle:10,1')->name('login.google.verify');
    Route::get('/login/google/phone', [\App\Http\Controllers\GoogleLoginController::class, 'phone'])->name('login.google.phone');
    Route::post('/login/google/phone', [\App\Http\Controllers\GoogleLoginController::class, 'savePhone'])->middleware('throttle:10,1')->name('login.google.phone.save');
    Route::post('/login/google/phone/skip', [\App\Http\Controllers\GoogleLoginController::class, 'skip'])->middleware('throttle:10,1')->name('login.google.phone.skip');
});

\Illuminate\Support\Facades\Route::post('/issues/{publicId}/observe', [\App\Http\Controllers\IssueObservationController::class, 'store'])->middleware(['auth', 'phone.verified', 'throttle:20,1'])->name('issues.observe');

\Illuminate\Support\Facades\Route::post('/issues/{publicId}/verify', [\App\Http\Controllers\IssueVerifyController::class, 'store'])->middleware(['auth', 'phone.verified', 'throttle:20,1'])->name('issues.verify');
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
\Illuminate\Support\Facades\Route::post('/fund/donate', [\App\Http\Controllers\FundDonateController::class, 'store'])->middleware('throttle:20,1')->name('fund.pay.store');
\Illuminate\Support\Facades\Route::get('/fund/donate/callback', [\App\Http\Controllers\FundDonateController::class, 'callback'])->name('fund.pay.callback');
\Illuminate\Support\Facades\Route::post('/fund/pay/ssl/success', [\App\Http\Controllers\FundDonateController::class, 'sslSuccess'])->name('fund.pay.ssl.success');
\Illuminate\Support\Facades\Route::post('/fund/pay/ssl/fail', [\App\Http\Controllers\FundDonateController::class, 'sslFail'])->name('fund.pay.ssl.fail');
\Illuminate\Support\Facades\Route::post('/fund/pay/ssl/cancel', [\App\Http\Controllers\FundDonateController::class, 'sslCancel'])->name('fund.pay.ssl.cancel');
\Illuminate\Support\Facades\Route::match(['get', 'post'], '/fund/pay/bkash/callback', [\App\Http\Controllers\FundDonateController::class, 'bkashCallback'])->name('fund.pay.bkash.callback');
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('/site', [\App\Http\Controllers\Admin\SiteController::class, 'index'])->name('site.index');
    \Illuminate\Support\Facades\Route::post('/site', [\App\Http\Controllers\Admin\SiteController::class, 'save'])->name('site.save');
});

\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin/funding')->name('admin.funding.')->group(function () {
    \Illuminate\Support\Facades\Route::get('/', [\App\Http\Controllers\Admin\FundingController::class, 'index'])->name('index');
    \Illuminate\Support\Facades\Route::post('/save', [\App\Http\Controllers\Admin\FundingController::class, 'save'])->name('save');
    \Illuminate\Support\Facades\Route::post('/manual', [\App\Http\Controllers\Admin\FundingController::class, 'manual'])->name('manual');
    \Illuminate\Support\Facades\Route::post('/donations/{donation}/void', [\App\Http\Controllers\Admin\FundingController::class, 'void'])->name('void');
    \Illuminate\Support\Facades\Route::post('/gateway', [\App\Http\Controllers\Admin\FundingController::class, 'saveGateway'])->name('gateway');
});
// ---- Admin: system info, cache, maintenance mode, backups, updates ----
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    \Illuminate\Support\Facades\Route::get('/system', [\App\Http\Controllers\Admin\SystemController::class, 'index'])->name('system.index');
    \Illuminate\Support\Facades\Route::post('/system/cache', [\App\Http\Controllers\Admin\SystemController::class, 'cache'])->name('system.cache');
    \Illuminate\Support\Facades\Route::post('/system/maintenance', [\App\Http\Controllers\Admin\SystemController::class, 'maintenance'])->name('system.maintenance');
    \Illuminate\Support\Facades\Route::post('/system/migrate', [\App\Http\Controllers\Admin\SystemController::class, 'migrate'])->name('system.migrate');
    \Illuminate\Support\Facades\Route::post('/system/backups', [\App\Http\Controllers\Admin\SystemController::class, 'backupCreate'])->name('system.backups.create');
    \Illuminate\Support\Facades\Route::get('/system/backups/{file}', [\App\Http\Controllers\Admin\SystemController::class, 'backupDownload'])->where('file', '[A-Za-z0-9._-]+')->name('system.backups.download');
    \Illuminate\Support\Facades\Route::post('/system/backups/{file}/delete', [\App\Http\Controllers\Admin\SystemController::class, 'backupDelete'])->where('file', '[A-Za-z0-9._-]+')->name('system.backups.delete');
    \Illuminate\Support\Facades\Route::post('/system/backups/restore', [\App\Http\Controllers\Admin\SystemController::class, 'backupRestore'])->name('system.backups.restore');
    \Illuminate\Support\Facades\Route::post('/system/auto-backup', [\App\Http\Controllers\Admin\SystemController::class, 'autoBackup'])->name('system.autobackup');
    \Illuminate\Support\Facades\Route::get('/audit-log', [\App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('auditlog.index');
    \Illuminate\Support\Facades\Route::get('/sponsors', [\App\Http\Controllers\Admin\SponsorController::class, 'index'])->name('sponsors.index');
    \Illuminate\Support\Facades\Route::post('/sponsors/save', [\App\Http\Controllers\Admin\SponsorController::class, 'save'])->name('sponsors.save');
    \Illuminate\Support\Facades\Route::post('/sponsors/add', [\App\Http\Controllers\Admin\SponsorController::class, 'add'])->name('sponsors.add');
    \Illuminate\Support\Facades\Route::post('/sponsors/delete', [\App\Http\Controllers\Admin\SponsorController::class, 'delete'])->name('sponsors.delete');
    \Illuminate\Support\Facades\Route::get('/footer-links', [\App\Http\Controllers\Admin\FooterLinksController::class, 'index'])->name('footer.index');
    \Illuminate\Support\Facades\Route::post('/footer-links/save', [\App\Http\Controllers\Admin\FooterLinksController::class, 'save'])->name('footer.save');
    \Illuminate\Support\Facades\Route::post('/footer-links/add', [\App\Http\Controllers\Admin\FooterLinksController::class, 'add'])->name('footer.add');
    \Illuminate\Support\Facades\Route::post('/footer-links/delete', [\App\Http\Controllers\Admin\FooterLinksController::class, 'delete'])->name('footer.delete');
    \Illuminate\Support\Facades\Route::get('/integrations', [\App\Http\Controllers\Admin\IntegrationController::class, 'index'])->name('integrations.index');
    \Illuminate\Support\Facades\Route::post('/integrations/push', [\App\Http\Controllers\Admin\IntegrationController::class, 'savePush'])->name('integrations.push');
    \Illuminate\Support\Facades\Route::post('/integrations/share', [\App\Http\Controllers\Admin\IntegrationController::class, 'saveShare'])->name('integrations.share');
    \Illuminate\Support\Facades\Route::post('/integrations/digest', [\App\Http\Controllers\Admin\IntegrationController::class, 'saveDigest'])->name('integrations.digest');
});

require __DIR__.'/manifest.php';

// ---- Funding: expenses and vouchers ----
\Illuminate\Support\Facades\Route::middleware('auth')->prefix('admin/funding')->name('admin.funding.')->group(function () {
    \Illuminate\Support\Facades\Route::post('/expenses', [\App\Http\Controllers\Admin\FundExpenseController::class, 'store'])->name('expense.store');
    \Illuminate\Support\Facades\Route::post('/expenses/{expense}/delete', [\App\Http\Controllers\Admin\FundExpenseController::class, 'destroy'])->name('expense.delete');
});
