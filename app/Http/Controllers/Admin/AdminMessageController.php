<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMessage;
use App\Models\Guardian;
use App\Models\User;
use App\Support\ReviewerScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Staff talking to one member, or one trusted contact.
 *
 * ⚠️ THE RELAY PROHIBITION IS RENDERED, NOT TRAINED.
 *
 * "Admin never relays a message between two members" is a rule that lives or
 * dies on whether the person about to do it remembers. Training does not
 * survive a busy afternoon and a member asking a reasonable question. So the
 * sentence is sent to the interface and sits above the compose box, where it is
 * read at the moment it applies rather than in an induction six months earlier.
 *
 * ⚠️ THE SCHEMA CANNOT ENFORCE IT. A thread has one counterparty, so there is
 * no way to address two members — but nothing stops staff reading one thread
 * and typing its contents into another. That is the failure this text exists
 * for, and it is honest to say the control is a sentence rather than a
 * constraint.
 */
class AdminMessageController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        $this->assertInScope($request, $user);

        $messages = AdminMessage::where('counterparty_id', $user->id)
            ->with('author:id,name')
            ->orderBy('created_at')
            ->get()
            ->map(fn (AdminMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'fromStaff' => $m->from_staff,
                'author' => $m->author?->name,
                'at' => $m->created_at->format('j F Y, H:i'),
            ]);

        $guardian = Guardian::where('user_id', $user->id)->first();

        return Inertia::render('Admin/Messages/Show', [
            'counterparty' => [
                'id' => $user->id,
                'name' => $user->profile?->first_name ?? $user->name,
                'isTrustedContact' => $guardian !== null,
            ],
            'messages' => $messages,

            // ⚠️ Sent to the browser so it is rendered above the compose box.
            'relayProhibition' => AdminMessage::RELAY_PROHIBITION,
        ]);
    }

    public function store(Request $request, User $user): RedirectResponse
    {
        $this->assertInScope($request, $user);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        AdminMessage::create([
            'counterparty_id' => $user->id,
            'author_id' => $request->user()->id,
            'from_staff' => true,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Sent.');
    }

    /**
     * ⚠️ GENDER SEPARATION APPLIES TO CONVERSATIONS TOO.
     *
     * A reviewer who cannot see a member's photographs has no business opening
     * a thread with her either. Staff without a reviewer role — and super
     * admin — are unrestricted, same as everywhere else.
     */
    private function assertInScope(Request $request, User $user): void
    {
        $guardian = Guardian::where('user_id', $user->id)->with('member.profile')->first();

        // For a trusted contact, scope on the gender of the member they serve.
        $gender = $guardian
            ? $guardian->member?->profile?->gender
            : $user->profile?->gender;

        abort_unless(ReviewerScope::maySee($request->user(), $gender), 404);
    }
}
