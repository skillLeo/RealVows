<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\VerificationController;
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

/*
 * Served rather than static, because it must answer differently on the review
 * build. While the one-click sign-in is open, nothing here should be indexed —
 * a search result pointing at a page with an admin button on it is a door
 * anyone can walk through. Reverts to normal automatically when it goes live.
 */
Route::get('/robots.txt', function () {
    $body = config('realvows.is_demo')
        ? "User-agent: *\nDisallow: /\n"
        : "User-agent: *\nDisallow: /admin\nDisallow: /profile\nDisallow: /dashboard\nDisallow: /account\n";

    return response($body, 200, ['Content-Type' => 'text/plain']);
})->name('robots');

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
    Route::middleware('admin:verifications.view')->group(function () {
        Route::get('/verifications', [VerificationController::class, 'index'])->name('verifications');
        Route::get('/verifications/{verification}', [VerificationController::class, 'show'])->name('verifications.show');

        Route::get('/verifications/{verification}/photo/{photo}', [VerificationController::class, 'photo'])
            ->middleware('signed')
            ->name('verifications.photo');

        Route::get('/verifications/{verification}/selfie', [VerificationController::class, 'selfie'])
            ->middleware('signed')
            ->name('verifications.selfie');
    });

    Route::post('/verifications/{verification}/review', [VerificationController::class, 'review'])
        ->middleware('admin:verifications.review')
        ->name('verifications.review');

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
