<?php

namespace App\Http\Middleware;

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
                    'is_admin' => $user->hasAnyRole(['admin', 'super_admin']),
                ] : null,
            ],

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
