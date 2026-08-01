<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            // Empty on anything that is not a local demo, so the panel does
            // not render at all rather than rendering and being disabled.
            'demoAccounts' => DemoLoginController::buttons(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            // One message for both a wrong password and an unknown address:
            // saying which was wrong tells an attacker whether an email is
            // registered on a marriage platform, which is not their business.
            throw ValidationException::withMessages([
                'email' => 'Those details do not match our records.',
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();

            throw ValidationException::withMessages([
                'email' => 'This account is closed. Please contact support.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    // SymfonyResponse, not Inertia\Response: Inertia::location() hands back a
    // 409 carrying an X-Inertia-Location header, which is a plain HTTP
    // response rather than a rendered page.
    public function destroy(Request $request): SymfonyResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        /*
         * Inertia::location, not redirect().
         *
         * Logout is triggered from an Inertia page, so the response comes back
         * over XHR. The home page is plain Blade — not an Inertia response —
         * and Inertia cannot consume it, so a normal redirect made it pop the
         * whole marketing page into a white error modal instead of navigating.
         * location() tells the browser to do a real visit, which is what
         * leaving the app actually is.
         */
        if ($request->header('X-Inertia')) {
            return Inertia::location(route('home'));
        }

        return redirect()->route('home');
    }
}
