<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Props shared with every Inertia response.
     *
     * ⚠️ Nothing derived from the internal sign calculation may ever be
     * shared here. The profile's `sign` and `element` columns are
     * server-side only and must never reach the browser.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'first_name' => $user->profile?->first_name,
                ] : null,
            ],

            /*
             * Navigation is decided on the server, where the permissions
             * actually live, and rendered verbatim by every piece of chrome.
             *
             * `is_admin` deliberately no longer travels with the user. It was
             * a role check standing in for a permission check, and every
             * place it was read had to guess which permissions it implied —
             * which is how a reviewer ended up with a Settings tab they could
             * not open. Nothing in the browser needs to know a person's role;
             * it needs to know where they may go, and that is this list.
             */
            'nav' => Navigation::for($user),

            // Drives the review-build banner. Same switch as the one-click
            // logins, so the banner cannot be showing while they are off, or
            // the other way round.
            'isDemo' => (bool) config('realvows.is_demo'),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
