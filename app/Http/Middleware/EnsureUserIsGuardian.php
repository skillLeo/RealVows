<?php

namespace App\Http\Middleware;

use App\Models\Guardian;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the trusted-contact area.
 *
 * ⚠️ 404, NEVER 403, consistent with /admin. A member who stumbles onto this
 * prefix learns nothing about who else has accounts here.
 *
 * ⚠️ IT REQUIRES A LIVE GUARDIAN RECORD, NOT JUST THE ROLE. A guardian who has
 * been replaced keeps their account and their role — the row is marked
 * `replaced` rather than deleted, because the decision history is evidence and
 * deleting it would erase the reason somebody was replaced. The role alone
 * would let a replaced guardian keep approving introductions for a member who
 * asked for somebody else.
 */
class EnsureUserIsGuardian
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(404);
        }

        $active = Guardian::where('user_id', $user->id)
            ->whereNot('status', Guardian::STATUS_REPLACED)
            ->exists();

        abort_unless($active, 404);

        return $next($request);
    }
}
