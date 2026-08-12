<?php

namespace App\Http\Controllers;

use App\Models\GuardianInvitation;
use App\Services\Guardian\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where an invited trusted contact creates their account.
 *
 * ⚠️ PUBLIC BY NECESSITY. The person opening this link has no account; making
 * one is the purpose. The token is the whole credential — looked up by hash,
 * single use, expiring — and there is no id in the URL to enumerate.
 */
class TrustedContactAcceptanceController extends Controller
{
    public function __construct(private readonly GuardianService $service) {}

    public function show(string $token): Response
    {
        $invitation = GuardianInvitation::findByToken($token);

        /*
         * ⚠️ 404 FOR WRONG, USED AND EXPIRED ALIKE.
         *
         * Distinguishing them would tell somebody guessing tokens that they
         * had found a real one that happened to be spent, which is the only
         * feedback a guesser needs. `findByToken` already filters all three
         * cases, so there is nothing here to accidentally reveal.
         */
        abort_unless($invitation, 404);

        $guardian = $invitation->guardian;

        return Inertia::render('TrustedContact/Accept', [
            'token' => $token,
            'memberName' => $guardian->member?->profile?->first_name,
            'relationship' => \App\Models\Guardian::RELATIONSHIPS[$guardian->relationship] ?? null,
            'suggestedName' => $guardian->name,
            'suggestedEmail' => $guardian->email,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->numbers()],
        ]);

        $guardian = $this->service->accept(
            $token,
            $validated['name'],
            $validated['email'],
            $validated['password'],
        );

        abort_unless($guardian, 404);

        /*
         * Signed straight in. They have just proved they hold the invitation
         * and chosen a password; asking them to log in again immediately is a
         * step that only loses people.
         */
        Auth::login($guardian->user);

        return redirect()->route('guardian.dashboard')
            ->with('success', 'Thank you. There is one more step to confirm who you are.');
    }
}
