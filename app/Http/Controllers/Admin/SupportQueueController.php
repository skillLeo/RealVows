<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\SupportTicket;
use App\Support\ReviewerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The admin inbox.
 *
 * ⚠️ UNFAIR-CONTACT TICKETS COME FIRST, ALWAYS.
 *
 * Every other type is somebody waiting for an answer. That one is somebody
 * whose introductions have stopped, who cannot see why, and who cannot do
 * anything about it herself. Sorting by date would put her behind a billing
 * question raised an hour earlier.
 *
 * ⚠️ REPLIES GO THROUGH THE EXISTING MESSAGE THREAD, which is where the relay
 * prohibition is rendered. A second reply box here would be a second inbox and
 * a reason to answer in the one without the rule on it.
 */
class SupportQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $tickets = SupportTicket::where('status', SupportTicket::STATUS_OPEN)
            ->with('user.profile')
            ->get()
            ->filter(function (SupportTicket $ticket) use ($user) {
                // A trusted contact's ticket is scoped on the member they serve.
                $guardian = Guardian::where('user_id', $ticket->user_id)->with('member.profile')->first();
                $gender = $guardian
                    ? $guardian->member?->profile?->gender
                    : $ticket->user->profile?->gender;

                return ReviewerScope::maySee($user, $gender);
            })
            /*
             * ⚠️ THE ORDERING IS THE POLICY. Unfair-contact first, then oldest.
             */
            ->sortBy(fn (SupportTicket $t) => [
                $t->type === 'guardian_unfair' ? 0 : 1,
                $t->created_at->timestamp,
            ])
            ->values()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'type' => $t->type,
                'label' => $t->label(),
                'isUnfairContact' => $t->type === 'guardian_unfair',
                'from' => $t->user->profile?->first_name ?? $t->user->name,
                'userId' => $t->user_id,
                'body' => $t->body,
                'at' => $t->created_at->format('j F Y, H:i'),
            ]);

        return Inertia::render('Admin/Support/Index', ['tickets' => $tickets]);
    }

    public function resolve(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $ticket->forceFill([
            'status' => SupportTicket::STATUS_RESOLVED,
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ])->save();

        return back()->with('success', 'Closed.');
    }
}
