<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientCredits;
use App\Exceptions\MatchAlreadyUnlocked;
use App\Jobs\AnnounceMutualInterest;
use App\Jobs\AnnounceSomeoneInterested;
use App\Models\MatchRecord;
use App\Services\Credits\CreditService;
use App\Services\Matching\MatchInterest;
use App\Support\FreePeriod;
use App\Support\MatchPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MatchController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly MatchPresenter $presenter,
    ) {}

    /**
     * The full view of one introduction — unlocked only.
     *
     * A teaser answers 404 rather than 403. 403 would confirm the row exists
     * and that there is something worth paying for behind it; 404 is the
     * honest answer as far as an unpaid member is concerned.
     */
    public function show(Request $request, MatchRecord $match): Response
    {
        $match = $this->ownedMatch($request, $match);

        abort_unless($match->isUnlocked(), 404);

        $match->load(['matched.photos', 'matched.preferences', 'matched.verification']);

        /*
         * ⚠️ The payload is built BEFORE `opened_at` is stamped.
         *
         * `firstView` is derived from that column, and stamping it first
         * would make every visit look like a repeat — the ring would never
         * draw and the score would never count, including the one time it
         * should. Order matters here in a way that is easy to reverse by
         * accident while tidying.
         *
         * ⚠️ `viewed_at` IS NOT STAMPED HERE ANY MORE. It belongs to the
         * presenter, because the presenter is the emission point and the
         * dashboard emits the same payload through forCard(). Recording it
         * here was the bug: one of two call sites remembered, and nothing made
         * the other one fail.
         */
        $payload = $this->presenter->full($match);

        if (! $match->opened_at) {
            $match->forceFill(['opened_at' => now()])->save();
        }

        return Inertia::render('Matches/Show', ['match' => $payload]);
    }

    /**
     * Spend one credit to open one introduction.
     *
     * Both failure modes are kind: no credits is an invitation, and an
     * already-open match reassures the member they were not charged twice.
     */
    public function unlock(Request $request, MatchRecord $match): RedirectResponse
    {
        $match = $this->ownedMatch($request, $match);

        try {
            /*
             * ⚠️ THE MODE IS CHECKED HERE, NOT IN THE SERVICE.
             *
             * The two paths write different ledger reasons and only one of
             * them stamps the permanence marker, so they are genuinely
             * different operations rather than one with a flag. Deciding at
             * the edge keeps CreditService free of a branch that would have to
             * be remembered by every future caller.
             */
            if (app(FreePeriod::class)->isActive()) {
                $this->credits->unlockMatchFree($request->user(), $match);
            } else {
                $this->credits->unlockMatch($request->user(), $match);
            }
        } catch (InsufficientCredits $e) {
            return back()->with('error', $e->getMessage());
        } catch (MatchAlreadyUnlocked $e) {
            return redirect()->route('matches.show', $match)->with('error', $e->getMessage());
        }

        /*
         * The unsealing is presentation only. The credit is already spent and
         * the status is already unlocked before any animation begins — if the
         * member closes the tab mid-ceremony they lose nothing and simply
         * land on an open card next time.
         */
        return redirect()->route('dashboard')->with('unsealed', [$match->id]);
    }

    /**
     * The member's answer to an introduction: interested, or pass.
     *
     * ⚠️ THE RESPONSE TO BOTH ANSWERS IS DELIBERATELY THE SAME SHAPE.
     *
     * A pass is never disclosed to the other person — no notification, no
     * change to their card, nothing they could infer an absence from. That
     * promise is only worth as much as the quietest channel keeps it, so
     * neither answer produces anything the other side can observe.
     */
    public function respond(Request $request, MatchRecord $match, MatchInterest $interest): RedirectResponse
    {
        $match = $this->ownedMatch($request, $match);

        $validated = $request->validate([
            'response' => ['required', 'in:'.MatchRecord::RESPONSE_INTERESTED.','.MatchRecord::RESPONSE_PASSED],
        ]);

        /*
         * 404, not 403, and not a redirect with a message. A teaser answering
         * anything other than "there is nothing here" would confirm the row
         * exists and is worth paying to open.
         */
        abort_unless($interest->canRespond($match), 404);

        if ($validated['response'] === MatchRecord::RESPONSE_PASSED) {
            $interest->pass($match);

            return redirect()->route('dashboard');
        }

        $mutual = $interest->express($match);

        /*
         * ⚠️ THE OTHER PERSON IS TOLD HERE AND NOWHERE ELSE, AND ONLY WHEN IT
         * IS MUTUAL. `express()` returns that answer rather than being asked a
         * second time, because two reads of the same question can disagree and
         * the disagreement that matters sends contact details to one person
         * before the other has agreed.
         */
        if ($mutual) {
            AnnounceMutualInterest::dispatch($match->id);

            return redirect()->route('matches.show', $match);
        }

        /*
         * ⚠️ NOT MUTUAL YET — SO THE OTHER PERSON IS TOLD SOMEBODY IS
         * INTERESTED, AND NOT WHO.
         *
         * This is the asymmetry that makes the product humane: a pass is
         * silent, interest is not. The recipient learns there is a reason to
         * come back and look, and learns nothing that identifies anybody or
         * that would let them infer who passed on them.
         *
         * Only sent when the reciprocal row exists — without one there is no
         * introduction on their side for them to answer, so a nudge would send
         * them to a dashboard with nothing new on it.
         */
        $reciprocal = $interest->reciprocal($match);

        if ($reciprocal && $reciprocal->response === null) {
            AnnounceSomeoneInterested::dispatch((int) $reciprocal->seeker->user_id);
        }

        return redirect()->route('matches.show', $match);
    }

    /**
     * Resolved from the signed-in member, never from the URL alone. There is
     * no id a member can change to see somebody else's introduction.
     */
    private function ownedMatch(Request $request, MatchRecord $match): MatchRecord
    {
        $profileId = $request->user()->profile?->id;

        abort_if($profileId === null, 404);
        abort_unless($match->seeker_profile_id === $profileId, 404);

        return $match;
    }
}
