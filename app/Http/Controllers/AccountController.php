<?php

namespace App\Http\Controllers;

use App\Actions\RecordAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        /*
         * ⚠️ THE TRUSTED-CONTACT ENTRY LIVES HERE, NOT IN THE TAB BAR.
         *
         * Navigation documents its own rule: the bar is four tabs, never five,
         * and reports were kept out of it for the same reason. A fifth tab
         * would have been the easy thing.
         *
         * ⚠️ AND IT IS ABSENT ENTIRELY WHEN THE POLICY DOES NOT OFFER IT. The
         * requirement is unset by design, so today no member sees this card.
         * Rendering it disabled would advertise a system the client has not
         * decided to switch on.
         */
        $policy = app(\App\Support\GuardianPolicy::class);
        $gender = $request->user()->profile?->gender;

        return Inertia::render('Account', [
            'trustedContact' => $policy->isOfferedTo($gender) ? [
                'required' => $policy->isRequiredFor($gender),
                'state' => app(\App\Services\Guardian\GuardianGate::class)->stateFor($request->user()),
            ] : null,

            'account' => [
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'phoneCountryCode' => $user->phone_country_code,
                'phoneNumber' => $user->phone_number,
                'phoneVerified' => $user->phoneIsVerified(),
            ],

            /*
             * ⚠️ Receipts are deliberately absent from this array. A receipt
             * for money taken is a record the member is entitled to, not a
             * marketing preference — the screen says so rather than offering
             * a toggle that would be dishonest to provide.
             */
            'notifications' => [
                'emailMatches' => (bool) ($user->notificationPreference?->email_matches ?? true),
                'whatsappMatches' => (bool) ($user->notificationPreference?->whatsapp_matches ?? false),
            ],
            'countryCodes' => Auth\RegisteredUserController::countryCodes(),
        ]);
    }

    /**
     * The WhatsApp toggle is honest about not being connected yet — Phase 3
     * wires the channel. A switch that silently does nothing is worse than
     * one that says when it will start working.
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_matches' => ['required', 'boolean'],
            'whatsapp_matches' => ['required', 'boolean'],
        ]);

        $request->user()->notificationPreference()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data
        );

        return back()->with('success', 'Saved.');
    }

    public function updateContact(Request $request, RecordAuditLog $audit): RedirectResponse
    {
        $validated = $request->validate([
            'phone_country_code' => ['required', 'string', 'max:8', 'regex:/^\+[0-9]{1,4}$/'],
            'phone_number' => ['required', 'string', 'max:32', 'regex:/^[0-9 \-]{6,}$/'],
        ], [
            'phone_number.regex' => 'Enter your mobile number using digits only.',
        ]);

        $user = $request->user();
        $changed = $user->phone_number !== $validated['phone_number']
            || $user->phone_country_code !== $validated['phone_country_code'];

        $user->fill($validated);

        // A new number is an unverified number. Carrying the old verification
        // across would mean matches going to whoever now owns that handset.
        if ($changed) {
            $user->phone_verified_at = null;
        }

        $user->save();

        if ($changed) {
            $audit->handle('account.phone_changed', $user);
        }

        return back()->with('success', 'Saved.');
    }

    public function updatePassword(Request $request, RecordAuditLog $audit): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ], [
            'password.min' => 'Password needs at least 8 characters, including one number.',
            'password.numbers' => 'Password needs at least 8 characters, including one number.',
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $user->update(['password' => $request->input('password')]);

        $audit->handle('account.password_changed', $user);

        return back()->with('success', 'Your password has been changed.');
    }
}
