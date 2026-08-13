<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\MatchRecord;
use App\Models\Profile;

/**
 * Everything a trusted contact is allowed to see, and nothing else.
 *
 * ══════════════════════════════════════════════════════════════════════════
 * ⚠️ THE MOST IMPORTANT RULE IN THE GUARDIAN SYSTEM
 * ══════════════════════════════════════════════════════════════════════════
 *
 * A FAMILY guardian — father, brother, uncle, grandfather — is a mahram. He is
 * performing a role his relationship already entitles him to perform, and he
 * may be shown the other party's photographs, because he cannot judge a match
 * for his daughter without seeing who she would be meeting.
 *
 * An APPOINTED guardian is a stranger. The platform put him in that seat
 * because the member had nobody to name. He has no relationship to her, and
 * showing him her photographs — or the other party's — creates exactly the
 * exposure this whole system was built to prevent. It would be worse than
 * having no guardian at all, because it would be the platform doing it.
 *
 * ── ⚠️ WHY THIS IS ENFORCED HERE AND NOT IN THE VIEW ──────────────────────
 *
 * A view that hides a photograph has still served it. The controller loaded
 * the record, the payload carried the path, the signed URL was generated and
 * sat in the page source. Hiding it in a template changes what is drawn and
 * nothing about what was sent. Anybody who opens the network tab has it.
 *
 * So the rule lives at the single point where a guardian-facing payload is
 * built. If a path is not put into the array here, there is nothing downstream
 * to leak. GuardianPhotoRuleTest asserts it with a positive control, because
 * an assertion that "no photo path appears" would also pass against a
 * presenter that returned an empty array for everybody.
 *
 * ⚠️ DO NOT ADD A PHOTOGRAPH TO A GUARDIAN PAYLOAD ANYWHERE ELSE. If a screen
 * needs one, it comes through here or it does not come.
 */
class GuardianPresenter
{
    /**
     * What a guardian sees about the person their ward has been introduced to.
     *
     * @return array<string, mixed>
     */
    public function introduction(Guardian $guardian, MatchRecord $match, Profile $other): array
    {
        $payload = [
            'matchId' => $match->id,
            'firstName' => $other->first_name,
            'age' => $other->date_of_birth?->age,
            'city' => $other->city,
            'country' => $other->country,
            'about' => $other->bio,
            'decidedAt' => null,
        ];

        /*
         * ⚠️ THE BRANCH. Photographs are ADDED for a guardian entitled to them
         * rather than removed for one who is not.
         *
         * That direction matters. "Unset the photos when appointed" fails open:
         * a new key added by somebody who has not read this file arrives in the
         * payload for everybody, and the appointed case leaks by default. This
         * way, a new photograph key that is not put inside this branch simply
         * does not reach anyone, which is the safe direction to be wrong in.
         *
         * ⚠️ THE SUBJECT IS NAMED, AND FOR A LONG TIME IT WAS NOT. This asked
         * `maySeePhotographs()` — appointed or not — and then handed over
         * `$other->photos` regardless of who $other was. A man's father or
         * brother, acting as his trusted contact, was shown the WOMAN'S
         * photographs. See Guardian::maySeePhotographsOf().
         */
        if ($guardian->maySeePhotographsOf($other)) {
            $payload['photos'] = $other->photos
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => route('guardian.photo', ['photo' => $photo->id]),
                ])
                ->all();
        } else {
            /*
             * Said plainly rather than left as a gap. A guardian seeing an
             * empty space would reasonably think the system is broken and ask
             * for the photographs.
             *
             * ⚠️ TWO REASONS, BECAUSE THEY ARE TWO DIFFERENT FACTS. Telling an
             * appointed contact "her photographs are not yours to see" is
             * correct; telling a father the same about his own daughter's
             * introduction would be wrong and would read as a fault.
             */
            $payload['photographsWithheld'] = true;
            $payload['photographsWithheldReason'] = $guardian->is_appointed
                ? 'Photographs are not shared with an appointed trusted contact. '
                    .'They are reviewed by our team.'
                : 'Photographs of a woman are shared only with her own trusted contact. '
                    .'Everything else about this introduction is here for you to weigh.';
        }

        /*
         * ⚠️ THE DETAILS COME HERE ONCE HE HAS APPROVED THE EXCHANGE, BECAUSE
         * HE IS THE ONE MAKING THE INTRODUCTION.
         *
         * The client's scholar ruled that a guardian approving an exchange is
         * not the same as a guardian being present for it, except where he is
         * unjust, is not Muslim, or has died. Outside those, the platform stops
         * being the channel and he becomes it.
         *
         * MatchPresenter now withholds the surname and the phone number from
         * the MEMBER when that applies. Withholding alone would leave the
         * introduction dead: she cannot proceed, he has nothing to pass on, and
         * the product simply stops at the last gate with nobody holding
         * anything. This is the other half, and neither half is correct alone.
         *
         * ⚠️ ONLY AFTER HIS OWN APPROVAL AT THE CONTACT GATE. A trusted contact
         * who has not yet decided must not be able to read a phone number by
         * opening the page — that would make his approval a formality he could
         * skip, which is the exact failure being corrected.
         *
         * ⚠️ AND NEVER TO AN APPOINTED CONTACT. He is a stranger, he does not
         * mediate, and the member receives her own details as before. Handing
         * him a woman's phone number to pass on would be a worse exposure than
         * the one this rule exists to prevent.
         */
        if (! $guardian->is_appointed && $guardian->hasApproved(Guardian::GATE_CONTACT, $match->id)) {
            // ⚠️ A SEPARATE NAME. Reassigning $other from a Profile to a User
            // works today and quietly changes the type under anything added
            // below it, which is how a photograph key ends up reading the wrong
            // record.
            $otherUser = $other->user;

            $payload['contact'] = array_filter([
                'lastName' => $other->last_name,
                'email' => $otherUser?->email,
                'phone' => $otherUser?->fullPhone(),
            ]);

            $payload['contactIsYoursToPass'] = true;
        }

        return $payload;
    }

    /**
     * The guardian's own ward, as the guardian sees them.
     *
     * ⚠️ AN APPOINTED GUARDIAN DOES NOT SEE HIS WARD'S PHOTOGRAPHS EITHER.
     * The rule is about the relationship, not about the direction. He is a
     * stranger to her as well.
     *
     * @return array<string, mixed>
     */
    public function ward(Guardian $guardian, Profile $ward): array
    {
        $payload = [
            'firstName' => $ward->first_name,
            'age' => $ward->date_of_birth?->age,
            'city' => $ward->city,
        ];

        if ($guardian->maySeePhotographsOf($ward)) {
            $payload['photos'] = $ward->photos
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => route('guardian.photo', ['photo' => $photo->id]),
                ])
                ->all();
        } else {
            $payload['photographsWithheld'] = true;
        }

        return $payload;
    }

    /**
     * ⚠️ THE GATE THE IMAGE ROUTE ASKS. Same rule, asked at the point the
     * bytes would be streamed, because a signed URL that escaped into a log or
     * a browser history must still be refused.
     *
     * ⚠️ IT TAKES THE SUBJECT, AND THE OLD ONE DID NOT. `mayStreamPhotograph($guardian)`
     * asked only whether he was appointed, so a family contact who reached the
     * route with any photo id belonging to a profile his ward had been matched
     * to was served the bytes — including a woman's, to a man with no
     * relationship to her. Withholding the URL upstream is not a rule while
     * the route below it still answers.
     */
    public function mayStreamPhotographOf(Guardian $guardian, ?Profile $subject): bool
    {
        return $guardian->maySeePhotographsOf($subject);
    }
}
