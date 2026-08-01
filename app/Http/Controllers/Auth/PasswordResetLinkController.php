<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Settings\RuntimeMailConfigurator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'sent' => session('reset_link_sent', false),
        ]);
    }

    public function store(Request $request, RuntimeMailConfigurator $mail): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $mail->apply();

        Password::sendResetLink($request->only('email'));

        // Always the same answer, whether or not the address is registered.
        // A different response for an unknown email would confirm who has an
        // account here, which on a marriage platform is genuinely sensitive.
        return back()->with('reset_link_sent', true);
    }
}
