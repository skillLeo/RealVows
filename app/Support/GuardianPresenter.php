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
         * ⚠️ THE BRANCH. Photographs are ADDED for a family guardian rather
         * than removed for an appointed one.
         *
         * That direction matters. "Unset the photos when appointed" fails open:
         * a new key added by somebody who has not read this file arrives in the
         * payload for everybody, and the appointed case leaks by default. This
         * way, a new photograph key that is not put inside this branch simply
         * does not reach anyone, which is the safe direction to be wrong in.
         */
        if ($guardian->maySeePhotographs()) {
            $payload['photos'] = $other->photos
                ->map(fn ($photo) => [
                    'id' => $photo->id,
                    'url' => route('guardian.photo', ['photo' => $photo->id]),
                ])
                ->all();
        } else {
            /*
             * Said plainly rather than left as a gap. An appointed guardian
             * seeing an empty space would reasonably think the system is
             * broken and ask for the photographs.
             */
            $payload['photographsWithheld'] = true;
            $payload['photographsWithheldReason'] =
                'Photographs are not shared with an appointed trusted contact. '
                .'They are reviewed by our team and shown to the member directly.';
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

        if ($guardian->maySeePhotographs()) {
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
     */
    public function mayStreamPhotograph(Guardian $guardian): bool
    {
        return $guardian->maySeePhotographs();
    }
}
