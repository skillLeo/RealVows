<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\DemoLoginController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PackagesController;
use App\Http\Controllers\PhotoController;
use App\Http\Controllers\ProfileBuilderController;
use Illuminate\Support\Facades\Route;

/*
 * ⚠️ One-click demo sign-in. The route is only REGISTERED when the config
 * allows it, so on staging or production this URL does not exist at all
 * rather than existing and being defended. See DemoLoginController.
 */
if (config('realvows.demo_logins')) {
    Route::get('/demo-login/{account}', DemoLoginController::class)->name('demo.login');
}

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:registration');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:login')
        ->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');

    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('verified')
        ->name('dashboard');

    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::post('/account/contact', [AccountController::class, 'updateContact'])->name('account.contact');
    Route::post('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    Route::middleware('verified')->group(function () {
        Route::get('/packages', [PackagesController::class, 'index'])->name('packages');

        Route::get('/profile', [ProfileBuilderController::class, 'show'])->name('profile');

        Route::get('/profile/{step}', [ProfileBuilderController::class, 'show'])->name('profile.step');

        // Autosave. PUT because it replaces the values of one step, and it is
        // hit repeatedly while somebody is still typing.
        Route::put('/profile/{step}', [ProfileBuilderController::class, 'save'])->name('profile.save');

        Route::post('/profile/submit', [ProfileBuilderController::class, 'submit'])->name('profile.submit');

        // Photos. Signed on top of auth on the read route, so a URL that
        // escapes into a chat log stops working within the hour.
        Route::post('/photos', [PhotoController::class, 'store'])->name('photos.store');
        Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
        Route::get('/photos/{photo}', [PhotoController::class, 'show'])
            ->middleware('signed')
            ->name('photos.show');

        Route::post('/verification/selfie', [PhotoController::class, 'storeVerificationSelfie'])
            ->name('verification.selfie');
        Route::get('/verification/selfie/{verification}', [PhotoController::class, 'showVerificationSelfie'])
            ->middleware('signed')
            ->name('verification.selfie.show');
        Route::post('/verification/code', [PhotoController::class, 'regenerateCode'])
            ->name('verification.code');
    });
});
