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

        return Inertia::render('Account', [
            'account' => [
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'phoneCountryCode' => $user->phone_country_code,
                'phoneNumber' => $user->phone_number,
                'phoneVerified' => $user->phoneIsVerified(),
            ],
            'countryCodes' => Auth\RegisteredUserController::countryCodes(),
        ]);
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
