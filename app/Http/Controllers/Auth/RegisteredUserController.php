<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Profile;
use App\Models\User;
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

    public function store(RegisterRequest $request): RedirectResponse
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

        /*
         * ⚠️ NO VERIFICATION EMAIL HERE, AND NO TRIP TO AN INBOX.
         *
         * `event(new Registered($user))` sent it, and the member was then
         * redirected to a page telling them to go and check their mail before
         * they could do anything at all.
         *
         * The client's objection, and he is right: "if someone has to go on
         * their email first to confirm their email, then they might not bother
         * with their profile." Somebody who has typed a password and nothing
         * else has invested nothing, and sending them away at that exact moment
         * is where they stop.
         *
         * The email now sends when the profile is FINISHED — see
         * ProfileBuilderController::submit. By then they have written about
         * themselves and uploaded photographs, and clicking a link is a small
         * thing at the end rather than a toll on the way in.
         *
         * ⚠️ THE GATE DID NOT DISAPPEAR, IT MOVED. Email verification was a
         * gate on entering the PRODUCT and is now a gate on entering the POOL,
         * enforced in Profile::scopeMatchable at the query layer. Read the note
         * there before relaxing anything here.
         */
        Auth::login($user);

        return redirect()->route('profile');
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
