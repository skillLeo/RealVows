<?php

namespace App\Services\Matching;

use App\Models\MatchRecord;
use Illuminate\Support\Facades\DB;

/**
 * Expressing interest, passing, and the one question that follows from both:
 * is it mutual?
 *
 * ⚠️ THIS IS THE MOST SENSITIVE CODE IN THE PRODUCT.
 *
 * Everything else in RealVows withholds information. This is the only thing
 * that reveals it — a last name, an email address and a phone number, handed
 * to exactly two people, at exactly one moment, and never before. Getting it
 * wrong does not produce a bug report; it produces a member whose phone
 * number reached somebody they did not agree to meet.
 *
 * ── ⚠️ THE MODEL: TWO ROWS, NOT ONE ───────────────────────────────────────
 *
 * A match is stored one direction per row. A→B and B→A are separate records,
 * written independently by the engine, and NEITHER IS GUARANTEED TO EXIST.
 * B may simply never have been matched with A.
 *
 * So mutual interest is not a flag on a row. It is a question asked of two
 * rows, and the answer is no unless both exist and both say interested. An
 * implementation that reads only the row in front of it will conclude
 * "mutual" from one person's click, which is the exact failure this class is
 * written to make impossible.
 *
 * ── ⚠️ INTEREST REQUIRES AN UNLOCKED CARD ─────────────────────────────────
 *
 * A teaser shows a first name, a city and a score. Expressing interest from
 * that is not consent to be introduced — the member has not seen who they
 * are agreeing to. Interest is only accepted on a card they have opened.
 */
class MatchInterest
{
    /**
     * The member says yes to this introduction.
     *
     * Returns whether the answer completed a mutual match, so the caller can
     * decide what to send without asking a second question and racing it.
     */
    public function express(MatchRecord $match): bool
    {
        $this->record($match, MatchRecord::RESPONSE_INTERESTED);

        return $this->isMutual($match);
    }

    /**
     * The member says no.
     *
     * ⚠️ A PASS IS FINAL AND IS NEVER TOLD TO THE OTHER PERSON.
     *
     * Nothing about a pass reaches the other side — no notification, no
     * change in what their card shows, no absence they could infer from. As
     * far as they are concerned nothing happened, which is the only humane
     * way to decline on a marriage platform where both people are real and
     * local to each other.
     */
    public function pass(MatchRecord $match): void
    {
        $this->record($match, MatchRecord::RESPONSE_PASSED);
    }

    /**
     * Have BOTH people said yes?
     *
     * ⚠️ THE RECIPROCAL ROW IS LOOKED UP BY ITS OWN PAIR OF IDS, NOT BY ANY
     * FIELD ON THIS ROW. There is no `reciprocal_id` to trust and no
     * relationship to traverse — the pairing is derived from the two profile
     * ids, reversed. If the other row does not exist, this is false.
     */
    public function isMutual(MatchRecord $match): bool
    {
        if ($match->response !== MatchRecord::RESPONSE_INTERESTED) {
            return false;
        }

        return $this->reciprocal($match)?->response === MatchRecord::RESPONSE_INTERESTED;
    }

    /**
     * The other direction of the same introduction, if the engine ever wrote
     * one. Null is a normal, common answer.
     */
    public function reciprocal(MatchRecord $match): ?MatchRecord
    {
        return MatchRecord::query()
            ->where('seeker_profile_id', $match->matched_profile_id)
            ->where('matched_profile_id', $match->seeker_profile_id)
            ->first();
    }

    /**
     * ⚠️ CAN THIS MEMBER ANSWER AT ALL?
     *
     * Kept as one predicate rather than scattered `if`s in the controller, so
     * the rule cannot drift between the route that shows the buttons and the
     * route that accepts the click.
     */
    public function canRespond(MatchRecord $match): bool
    {
        // Not seen, not answerable. A teaser is a first name and a score.
        if (! $match->isUnlocked()) {
            return false;
        }

        /*
         * ⚠️ AN ANSWER IS GIVEN ONCE.
         *
         * Not because changing your mind is unreasonable, but because the
         * other side may already have been told. Once contact details have
         * been handed over, a later "actually, no" cannot take them back, and
         * a button that appears to retract something it cannot retract is a
         * worse promise than no button.
         */
        return $match->response === null;
    }

    /**
     * ⚠️ WRITTEN ONCE, UNDER A LOCK, AND NEVER OVERWRITTEN.
     *
     * Two requests arriving together — a double-tap, or a retry after a slow
     * response — must not both write. The conditional update means the second
     * one changes no rows, so a member cannot flip a pass into interest by
     * clicking twice, and cannot re-trigger a notification either.
     */
    private function record(MatchRecord $match, string $response): void
    {
        DB::transaction(function () use ($match, $response) {
            $written = MatchRecord::query()
                ->where('id', $match->id)
                ->whereNull('response')
                ->update([
                    'response' => $response,
                    'responded_at' => now(),
                ]);

            if ($written > 0) {
                $match->forceFill(['response' => $response, 'responded_at' => now()]);
            } else {
                // Somebody else got there first. Take their answer, not ours.
                $match->refresh();
            }
        });
    }
}
