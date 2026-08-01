<?php

use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PaymentCallbackController;
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

/*
|--------------------------------------------------------------------------
| Payment callback — DELIBERATELY OUTSIDE EVERY SESSION MIDDLEWARE
|--------------------------------------------------------------------------
|
| ⚠️ DO NOT MOVE THIS INTO THE AUTHENTICATED GROUP. It will appear to work
| on localhost and fail on the live domain.
|
| The 3DS return is a cross-site POST from the issuing bank. With
| SESSION_SAME_SITE=lax — Laravel's default, and the right default — the
| browser does not send the session cookie on it, so the member arrives
| looking signed out at the exact moment their money is in flight.
|
| The wrong fix is SameSite=none, which weakens every cookie on the platform
| to solve one route. The right fix is this: no session, no CSRF, identify
| the purchase from our own order_identifier, and bounce to a signed URL on
| the session-bearing side afterwards.
|
| The signature on this route is what proves the request concerns this
| purchase, since there is no session to ask. The body is never trusted —
| the gateway is always re-queried before a credit is granted.
|
*/
Route::match(['get', 'post'], '/payments/callback/{order}', PaymentCallbackController::class)
    ->middleware('signed')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('checkout.callback');

require __DIR__.'/auth.php';
