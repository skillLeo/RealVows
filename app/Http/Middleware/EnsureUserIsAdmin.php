<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Gate for /admin.
     *
     * A member who reaches an admin URL gets a 404, never a 403.
     *
     * 403 confirms the route exists and says "you are not allowed" — which
     * tells anyone probing exactly where to keep pushing. 404 says the page
     * is not there at all, which is the honest answer as far as that member
     * is concerned and leaks nothing about the shape of the admin area.
     */
    public function handle(Request $request, Closure $next, ?string $permission = null): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(404);
        }

        if ($permission && ! $user->can($permission)) {
            abort(404);
        }

        return $next($request);
    }
}
