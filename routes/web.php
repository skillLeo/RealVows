<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public — plain Blade
|--------------------------------------------------------------------------
| No Vue, no Inertia, no hydration. These pages must be fast on a mid-range
| phone over 4G and crawlable by a search engine.
*/

Route::get('/', [PublicPageController::class, 'home'])->name('home');

Route::controller(LegalController::class)->group(function () {
    Route::get('/terms', 'terms')->name('legal.terms');
    Route::get('/privacy', 'privacy')->name('legal.privacy');
    Route::get('/community-guidelines', 'guidelines')->name('legal.guidelines');
});

/*
|--------------------------------------------------------------------------
| Administration — Inertia
|--------------------------------------------------------------------------
| Everything behind `admin` returns 404 rather than 403 to a member, so the
| existence of this area is never disclosed.
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::post('/settings/test', [SettingsController::class, 'test'])
        ->middleware('admin:settings.update')
        ->name('settings.test');

    Route::post('/settings', [SettingsController::class, 'update'])
        ->middleware('admin:settings.update')
        ->name('settings.update');

    Route::get('/settings/{group?}', [SettingsController::class, 'index'])
        ->middleware('admin:settings.view')
        ->name('settings');
});

require __DIR__.'/auth.php';
