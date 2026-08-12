<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\SupportTicket;
use App\Services\Guardian\GuardianGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where a member or a trusted contact asks for help.
 *
 * ⚠️ ONE FORM FOR BOTH, AND THE TYPE LIST DIFFERS.
 *
 * A trusted contact has no introductions and no billing, so offering them those
 * options is a form that does not know who is filling it in. A member gets the
 * unfair-contact route; a trusted contact does not, because it exists to
 * challenge them.
 */
class SupportController extends Controller
{
    public function __construct(private readonly GuardianGate $gate) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $isTrustedContact = Guardian::where('user_id', $user->id)->exists();

        return Inertia::render('Support/Show', [
            'types' => collect($this->typesFor($isTrustedContact))
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),

            /*
             * ⚠️ AUTO-SUGGESTED AFTER FIVE UNEXPLAINED DECLINES (B9).
             *
             * A member being repeatedly declined with no reason has no way of
             * knowing that asking for help is an option, because she cannot see
             * the decisions at all. Waiting for her to think of it herself is
             * waiting for somebody to complain about a process they have never
             * been shown.
             */
            'suggestUnfair' => ! $isTrustedContact && $this->gate->shouldOfferSupport($user),

            'open' => SupportTicket::where('user_id', $user->id)
                ->where('status', SupportTicket::STATUS_OPEN)
                ->latest()
                ->get()
                ->map(fn (SupportTicket $t) => [
                    'id' => $t->id,
                    'label' => $t->label(),
                    'at' => $t->created_at->format('j F Y'),
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isTrustedContact = Guardian::where('user_id', $user->id)->exists();

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys($this->typesFor($isTrustedContact)))],
            'body' => ['required', 'string', 'min:10', 'max:4000'],
        ]);

        SupportTicket::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'body' => $validated['body'],
            'status' => SupportTicket::STATUS_OPEN,
        ]);

        /*
         * ⚠️ NEVER "we will reply within X". Support is one person on a
         * platform that has not launched, and a promise about response time is
         * the easiest promise to break and the one a frustrated member
         * remembers.
         */
        return back()->with('success', 'Thank you. A person will read this and reply here.');
    }

    /** @return array<string, string> */
    private function typesFor(bool $isTrustedContact): array
    {
        $types = SupportTicket::TYPES;

        if ($isTrustedContact) {
            // They have no introductions, no billing, and the unfair-contact
            // route exists to challenge them.
            unset($types['guardian_unfair'], $types['billing'], $types['introduction']);
        }

        return $types;
    }
}
