<?php

namespace App\Http\Controllers\Admin;

use App\Actions\RecordAuditLog;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\GuardianDecision;
use App\Models\MatchRecord;
use App\Services\Guardian\GuardianGate;
use App\Services\Matching\MatchInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ⚠️ THE INTRODUCTIONS NOBODY IN THE FAMILY CAN MAKE.
 *
 * The scholar: "arranging conversation between the two is not permitted in
 * Shariah. Whatever necessary matters need to be satisfied should be handled
 * through the women of the household."
 *
 * A woman with a family trusted contact has somebody to carry that. A woman
 * with an APPOINTED contact does not — the appointed contact is a stranger the
 * platform placed in that seat, and handing him her details so he could pass
 * them on would be a worse exposure than the one being prevented.
 *
 * ⚠️ SO THE ALTERNATIVE WAS NEVER "NOBODY". Until now the product released the
 * man's surname, email and phone straight to her, on the reading that the
 * scholar's exception for an unjust, non-Muslim or dead guardian covered it. It
 * does not: "no family to name" is a different fact, and the ruling names no
 * exception to conversation between the two at all.
 *
 * The effect was that the member with the least family received the least
 * protection. Staff carry it instead, and are recorded by name as having done
 * so — because the ruling asks for presence rather than permission, and a row
 * saying "approved" would only record the second.
 */
class StaffIntroductionController extends Controller
{
    public function __construct(
        private readonly GuardianGate $gate,
        private readonly MatchInterest $interest,
    ) {}

    public function index(Request $request): Response
    {
        /*
         * ⚠️ THE QUEUE IS BUILT FROM MUTUAL INTEREST, NOT FROM A FLAG.
         *
         * A column saying "awaiting introduction" would be one more thing that
         * can drift out of step with what is actually true of the two rows.
         */
        $waiting = MatchRecord::query()
            ->where('response', MatchRecord::RESPONSE_INTERESTED)
            ->with(['seeker.user', 'matched.user'])
            ->latest('id')
            ->get()
            ->filter(function (MatchRecord $match) {
                $seeker = $match->seeker?->user;

                if (! $seeker || ! $this->interest->isMutual($match)) {
                    return false;
                }

                if ($this->gate->mediationFor($seeker) !== GuardianGate::MEDIATION_STAFF) {
                    return false;
                }

                return ! $this->alreadyMade($match);
            })
            ->take(50)
            ->map(fn (MatchRecord $match) => [
                'id' => $match->id,
                'her' => [
                    'name' => trim(($match->seeker?->first_name ?? '').' '.($match->seeker?->last_name ?? '')),
                    'email' => $match->seeker?->user?->email,
                    'phone' => $match->seeker?->user?->fullPhone(),
                ],
                'him' => [
                    'name' => trim(($match->matched?->first_name ?? '').' '.($match->matched?->last_name ?? '')),
                    'email' => $match->matched?->user?->email,
                    'phone' => $match->matched?->user?->fullPhone(),
                ],
                'score' => $match->total_score,
                'since' => $match->updated_at?->format('j M Y, H:i'),
            ])
            ->values();

        return Inertia::render('Admin/StaffIntroductions', [
            'waiting' => $waiting,
        ]);
    }

    public function store(Request $request, MatchRecord $match): RedirectResponse
    {
        $seeker = $match->seeker?->user;

        /*
         * ⚠️ THE SAME CONDITION THE QUEUE WAS BUILT FROM, ASKED AGAIN AT THE
         * POINT OF WRITING.
         *
         * A queue is a view of the world a moment ago. Between rendering and
         * submitting, a member can name a family contact, withdraw interest, or
         * be blocked, and posting this id by hand skips the list entirely.
         */
        abort_unless(
            $seeker
                && $this->interest->isMutual($match)
                && $this->gate->mediationFor($seeker) === GuardianGate::MEDIATION_STAFF,
            403,
            'This introduction is not one that staff carry.',
        );

        if ($this->alreadyMade($match)) {
            return back()->with('error', 'That introduction has already been made.');
        }

        $guardian = Guardian::where('member_id', $seeker->id)
            ->where('status', Guardian::STATUS_VERIFIED)
            ->firstOrFail();

        GuardianDecision::create([
            'guardian_id' => $guardian->id,
            'made_by_user_id' => $request->user()->id,
            'match_id' => $match->id,
            'gate' => Guardian::GATE_INTRODUCTION_MADE,
            'decision' => GuardianDecision::APPROVED,
            'reason' => $request->string('note')->toString() ?: null,
            'decided_at' => now(),
        ]);

        app(RecordAuditLog::class)->handle(
            'staff_introduction_made',
            $match,
            ['guardian_id' => $guardian->id],
            $request->user(),
        );

        return back()->with('success', 'Recorded. You are named on that introduction.');
    }

    private function alreadyMade(MatchRecord $match): bool
    {
        return GuardianDecision::where('match_id', $match->id)
            ->where('gate', Guardian::GATE_INTRODUCTION_MADE)
            ->exists();
    }
}
