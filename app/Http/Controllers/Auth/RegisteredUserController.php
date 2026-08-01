<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Profile;
use App\Models\User;
use App\Services\Settings\RuntimeMailConfigurator;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'countryCodes' => self::countryCodes(),
        ]);
    }

    public function store(RegisterRequest $request, RuntimeMailConfigurator $mail): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                // A display name is captured properly in the profile builder;
                // until then the local part of the email is a kinder default
                // than an empty string in a greeting.
                'name' => str($request->validated('email'))->before('@')->toString(),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'phone_country_code' => $request->validated('phone_country_code'),
                'phone_number' => $request->validated('phone_number'),
            ]);

            $user->assignRole('member');

            // The profile exists from the first moment so the builder always
            // has a row to autosave into — no "create on first keystroke"
            // race, and a draft is durable immediately.
            Profile::create(['user_id' => $user->id]);

            return $user;
        });

        // Mail settings live in the database, so they must be applied before
        // anything is sent rather than read from config at boot.
        $mail->apply();

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    /**
     * Deliberately short. The client is in the Caribbean and expects members
     * from there, from the diaspora, and from South Asia; a 200-entry select
     * would be a worse experience than five obvious choices plus room to grow.
     */
    public static function countryCodes(): array
    {
        return [
            ['value' => '+1', 'label' => '+1'],
            ['value' => '+44', 'label' => '+44'],
            ['value' => '+92', 'label' => '+92'],
            ['value' => '+91', 'label' => '+91'],
            ['value' => '+971', 'label' => '+971'],
        ];
    }
}
