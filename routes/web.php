<?php

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

// Language switch: /lang/bn or /lang/en
Route::get('/lang/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['bn', 'en'], true), 404);
    session(['locale' => $locale]);

    return back();
})->name('lang');