<?php

use App\Http\Controllers\Admin\AdminMessageController;
use App\Http\Controllers\Admin\CreditAdminController;
use App\Http\Controllers\Admin\EmailPreviewController;
use App\Http\Controllers\Admin\MatchAuditController;
use App\Http\Controllers\Admin\PaymentAdminController;
use App\Http\Controllers\Admin\ReportQueueController;
use App\Http\Controllers\Admin\StaffIntroductionController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TrustedContactQueueController;
use App\Http\Controllers\Admin\VerificationController;
use App\Http\Controllers\GuardianDashboardController;
use App\Http\Controllers\TrustedContactAcceptanceController;
use App\Http\Controllers\TrustedContactController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\Admin\SupportQueueController;
use App\Http\Controllers\ProfileWriterController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ResendWebhookController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\WhatsAppWebhookController;
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
 * ⚠️ REAL PAGES, NOT ANCHORS INTO THE HOME PAGE.
 *
 * The client's complaint was having to "scroll all the way down an entire
 * page" — an anchor link is the same scroll with a shortcut, and it leaves the
 * site feeling like one long document rather than a site. These are what the
 * navigation points at.
 *
 * `how-it-works` shares its three-step section with the home page through
 * public.partials.how-it-works, so there is one copy of that writing.
 */
Route::get('/how-it-works', [PublicPageController::class, 'howItWorks'])->name('how-it-works');
Route::get('/about', [PublicPageController::class, 'about'])->name('about');
Route::get('/questions', [PublicPageController::class, 'faq'])->name('faq');

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

/*
 * The offline page the service worker falls back to. Served rather than
 * static so it stays in one place with the rest of the routes, and cached at
 * install time so it is available precisely when nothing else is.
 */
Route::view('/offline', 'offline')->name('offline');

Route::controller(LegalController::class)->group(function () {
    Route::get('/terms', 'terms')->name('legal.terms');
    Route::get('/privacy', 'privacy')->name('legal.privacy');
    Route::get('/community-guidelines', 'guidelines')->name('legal.guidelines');

    /*
     * ⚠️ Required by a payment acquirer's underwriting, and absent until now.
     * Its absence is a gap regardless of when payments switch on.
     */
    Route::get('/refunds', 'refunds')->name('legal.refunds');
});

/*
|--------------------------------------------------------------------------
| Trusted contact (guardian)
|--------------------------------------------------------------------------
| A trusted contact has their own account and their own screens. They are NOT
| staff and must never reach /admin — `isAdmin()` deliberately omits the role.
|
| ⚠️ The photograph route asks GuardianPresenter, not the view. A signed URL
| that leaked into a browser history or a log must still be refused, so the
| rule is re-asked at the point the bytes would be streamed.
*/

/*
 * The member's own trusted-contact screens. Behind `auth`, and each action
 * 404s when the policy does not offer the feature to that member — a member who
 * finds this URL while the requirement is off should find nothing.
 */
Route::middleware('auth')->group(function () {
    /*
     * Suggests a tidier bio and saves nothing. The member chooses, and the
     * choice goes through the ordinary profile save.
     */
    Route::post('/profile/rewrite', ProfileWriterController::class)->name('profile.rewrite');

    // Support, for members and trusted contacts alike.
    Route::get('/support', [SupportController::class, 'show'])->name('support');
    Route::post('/support', [SupportController::class, 'store'])->name('support.store');

    Route::get('/trusted-contact', [TrustedContactController::class, 'show'])->name('trusted-contact.show');
    Route::post('/trusted-contact', [TrustedContactController::class, 'store'])->name('trusted-contact.store');
    Route::post('/trusted-contact/appointed', [TrustedContactController::class, 'requestAppointed'])
        ->name('trusted-contact.appointed');
});

/*
 * ⚠️ ACCEPTANCE IS PUBLIC AND MUST BE. The person opening this link has no
 * account — creating one is the entire purpose. Putting it behind `auth` would
 * make the invitation unusable by the only people it is sent to.
 *
 * The token is the whole credential: looked up by hash, single use, expiring.
 * There is no id in the URL to enumerate.
 */
Route::get('/trusted-contact/accept/{token}', [TrustedContactAcceptanceController::class, 'show'])
    ->name('trusted-contact.accept');
Route::post('/trusted-contact/accept/{token}', [TrustedContactAcceptanceController::class, 'store'])
    ->name('trusted-contact.accept.store');

Route::middleware(['auth', 'guardian'])->prefix('trusted-contact-dashboard')->name('guardian.')->group(function () {
    Route::get('/', [GuardianDashboardController::class, 'index'])->name('dashboard');
    Route::post('/decisions/{match}', [GuardianDashboardController::class, 'decide'])->name('decide');
    Route::get('/photo/{photo}', [GuardianDashboardController::class, 'photo'])->name('photo');

    // The joint photograph. The code is issued on first view of this page.
    Route::get('/confirm', [GuardianDashboardController::class, 'joint'])->name('joint');
    Route::post('/confirm', [GuardianDashboardController::class, 'storeJoint'])->name('joint.store');
});

/*
|--------------------------------------------------------------------------
| Administration — Inertia
|--------------------------------------------------------------------------
| Everything behind `admin` returns 404 rather than 403 to a member, so the
| existence of this area is never disclosed.
*/

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    /*
     * ⚠️ THE OVERVIEW SITS BEHIND THE WEAKEST STAFF PERMISSION ON PURPOSE.
     *
     * Every figure on it is already scoped by ReviewerScope, so a reviewer
     * sees their own half and is told so on the page. Gating it on something
     * stronger would leave the first screen in the sidebar 404ing for the
     * people who live in the sidebar.
     */
    Route::middleware('admin:verifications.view')->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        /*
         * ⚠️ AN ALIAS THAT REDIRECTS RATHER THAN RENDERS.
         *
         * /admin/dashboard is what anybody types who has ever used another
         * admin panel, and a 404 there is a bad first impression. Rendering the
         * same page at two URLs would be worse: two addresses for one screen
         * means bookmarks disagree, the sidebar's active state has to know
         * about both, and any future analytics counts one visit as two.
         */
        Route::redirect('/dashboard', '/admin');
    });

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

    /*
     * Concerns members have raised. Sits with the verification queue because
     * it is the same job — a person deciding whether somebody belongs here.
     */
    /*
     * Trusted contacts awaiting review, and members waiting for us to appoint
     * one. Same permission as verification because it is the same job.
     */
    Route::middleware('admin:verifications.review')->group(function () {
        Route::get('/trusted-contacts', [TrustedContactQueueController::class, 'index'])
            ->name('trusted-contacts');
        Route::post('/trusted-contacts/{guardian}', [TrustedContactQueueController::class, 'review'])
            ->name('trusted-contacts.review');
        Route::get('/trusted-contacts/{guardian}/photo', [TrustedContactQueueController::class, 'photo'])
            ->name('trusted-contacts.photo');

        /*
         * Staff talking to one member or one trusted contact. There is no
         * member-to-member route and the schema has no shape for one.
         */
        Route::get('/support', [SupportQueueController::class, 'index'])->name('support');
        Route::post('/support/{ticket}', [SupportQueueController::class, 'resolve'])->name('support.resolve');

        Route::get('/messages/{user}', [AdminMessageController::class, 'show'])->name('messages');
        Route::post('/messages/{user}', [AdminMessageController::class, 'store'])->name('messages.store');
    });

    Route::middleware('admin:verifications.review')->group(function () {
        Route::get('/reports', [ReportQueueController::class, 'index'])->name('reports');
        Route::post('/reports/{report}', [ReportQueueController::class, 'resolve'])->name('reports.resolve');
    });

    /*
     * Match audit. The only place the six component scores are visible, and
     * only to staff holding matches.view.
     */
    Route::middleware('admin:matches.view')->group(function () {
        Route::get('/matches', [MatchAuditController::class, 'index'])->name('matches');
        Route::get('/matches/{match}', [MatchAuditController::class, 'show'])->name('matches.show');
    });

    /*
     * ⚠️ THE INTRODUCTIONS NOBODY IN THE FAMILY CAN MAKE.
     *
     * A woman with an appointed trusted contact has no relative to carry an
     * introduction, and the scholar's ruling forbids the two speaking directly.
     * Staff carry it and are recorded by name as having been present. See
     * StaffIntroductionController for why an approval row was not enough.
     */
    Route::middleware('admin:verifications.review')->group(function () {
        Route::get('/introductions', [StaffIntroductionController::class, 'index'])
            ->name('introductions');
        Route::post('/introductions/{match}', [StaffIntroductionController::class, 'store'])
            ->name('introductions.made');
    });

    /*
     * Money. super_admin only — a reviewer brought in to check photographs
     * has no business voiding transactions.
     */
    Route::middleware('admin:settings.update')->group(function () {
        Route::get('/payments', [PaymentAdminController::class, 'index'])->name('payments');
        Route::post('/payments/{purchase}/requery', [PaymentAdminController::class, 'requery'])->name('payments.requery');
        Route::post('/payments/{purchase}/void', [PaymentAdminController::class, 'void'])->name('payments.void');
    });

    Route::middleware('admin:credits.adjust')->group(function () {
        Route::get('/users/{user}/credits', [CreditAdminController::class, 'show'])->name('credits');
        Route::post('/users/{user}/credits', [CreditAdminController::class, 'store'])->name('credits.store');
    });

    /*
     * Email previews.
     *
     * ⚠️ Behind `settings.update` rather than a lower permission, because the
     * send form will deliver a real message to any address typed into it. A
     * reviewer brought in to check photographs has no business doing that.
     */
    Route::middleware('admin:settings.update')->group(function () {
        Route::get('/emails', [EmailPreviewController::class, 'index'])->name('emails');
        Route::post('/emails/send', [EmailPreviewController::class, 'send'])->name('emails.send');
        Route::get('/emails/{key}', [EmailPreviewController::class, 'show'])->name('emails.show');
    });

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
    /*
     * ⚠️ PreventRequestForgery IS THE ONE THAT MATTERS. VerifyCsrfToken is now
     * a subclass of it, and the web group registers the parent — so excluding
     * only VerifyCsrfToken left this route fully CSRF-protected while reading
     * as though it were exempt.
     *
     * That is exactly the failure this route cannot survive: the 3DS return is
     * a cross-site POST from the issuing bank carrying no session cookie and no
     * token, so it would have been rejected with 419 at the moment a member's
     * money was in flight. It was never caught because CSRF disables itself
     * under runningUnitTests(), so every test passed.
     */
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
    ])
    ->name('checkout.callback');

/*
|--------------------------------------------------------------------------
| WhatsApp webhook — the handshake only
|--------------------------------------------------------------------------
|
| ⚠️ BOTH VERBS ARE REQUIRED, AND THE VERIFICATION IS THE **GET**.
|
| Meta verifies a callback URL with a GET carrying hub.challenge, and only
| sends real deliveries as POST. Registering POST alone answers 405 to the
| verification attempt, and Meta reports that as an unhelpful "could not
| verify" with no mention of the method.
|
| ⚠️ NO SESSION, AND NOT ONLY BECAUSE OF CSRF. This is a server-to-server
| call from Meta with no cookie and no user. Leaving StartSession in place
| would mint and store a throwaway session row for every delivery, which on
| a shared host with a database session driver is a table that grows for no
| reason at all.
|
| ⚠️ THE THROTTLE IS A TRADE-OFF, NOT A FREE SAFETY NET. Meta delivers in
| bursts and treats a 429 as a failure: it retries with backoff and disables
| a subscription that keeps refusing. 100/minute per IP was specified and is
| comfortably above anything this product will generate, but if deliveries
| ever start going missing, this line is the first place to look.
|
| Phase 3 owns message handling. The POST here acknowledges and logs.
|
*/
/*
 * ⚠️ THE MIDDLEWARE GOES BEFORE ->group(), NOT AFTER IT.
 *
 * Chaining ->middleware() onto the result of ->group() reads perfectly and
 * does nothing: group() registers the routes immediately, using the attributes
 * present at that moment, so anything added afterwards applies to nothing.
 * Written the wrong way round this route kept full CSRF protection and
 * answered 419 to every delivery from Meta — while the test suite passed,
 * because VerifyCsrfToken disables itself under runningUnitTests().
 */
Route::prefix('webhook')
    ->name('whatsapp.webhook.')
    ->middleware('throttle:whatsapp-webhook')
    ->withoutMiddleware([
        /*
         * ⚠️ PreventRequestForgery, NOT VerifyCsrfToken.
         *
         * VerifyCsrfToken still exists but is now a SUBCLASS of
         * PreventRequestForgery, and the web group registers the parent.
         * Excluding the subclass excludes nothing — the route keeps full CSRF
         * protection and answers 419 to every cross-site POST. Both are listed
         * so this cannot silently reverse if the framework renames it back.
         */
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ])
    ->group(function () {
        Route::get('/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('verify');
        Route::post('/whatsapp', [WhatsAppWebhookController::class, 'receive'])->name('receive');
    });

/*
|--------------------------------------------------------------------------
| Resend delivery events
|--------------------------------------------------------------------------
|
| ⚠️ Same shape as the WhatsApp webhook and for the same reasons: no session,
| no CSRF, acknowledge fast, queue the work, and treat an unverifiable payload
| as inert rather than rejecting it.
|
| This endpoint is what lets the ledger stop saying "accepted" and start saying
| "delivered" — see docs/EMAIL-DELIVERABILITY.md.
|
*/
Route::post('/webhook/resend', ResendWebhookController::class)
    ->middleware('throttle:whatsapp-webhook')
    ->withoutMiddleware([
        \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \App\Http\Middleware\HandleInertiaRequests::class,
    ])
    ->name('webhook.resend');

/*
|--------------------------------------------------------------------------
| Unsubscribing, without a session
|--------------------------------------------------------------------------
|
| ⚠️ SIGNED RATHER THAN AUTHENTICATED, for the same reason as the receipt: it
| opens from a mail client with no session. An unsubscribe link behind a login
| is the commonest reason a legitimate sender gets marked as spam, and a spam
| complaint costs the whole domain's deliverability rather than one message.
|
| ⚠️ THE GET DOES NOT ACT. Mail clients and corporate scanners follow every
| link in an email before a human sees it, so a GET that unsubscribed would
| silently switch off members who never clicked anything.
|
*/
Route::middleware('signed')->group(function () {
    Route::get('/unsubscribe/{user}/{channel}', [UnsubscribeController::class, 'show'])
        ->name('unsubscribe');

    Route::post('/unsubscribe/{user}/{channel}', [UnsubscribeController::class, 'update'])
        ->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ])
        ->name('unsubscribe.confirm');
});

/*
 * The receipt.
 *
 * Signed rather than authenticated, so it opens from an email client with no
 * session — which is the entire reason it exists as a page rather than only
 * as an email body. Ownership is still checked when somebody IS signed in.
 */
Route::get('/receipt/{order}', [ReceiptController::class, 'show'])
    ->middleware('signed')
    ->name('receipt');

require __DIR__.'/auth.php';
