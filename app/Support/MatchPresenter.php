<?php

namespace App\Support;

use App\Models\MatchRecord;
use App\Models\Photo;
use Illuminate\Support\Facades\URL;

/**
 * ⚠️ THE ONLY THING PERMITTED TO BUILD A MEMBER-FACING MATCH PAYLOAD.
 *
 * Two rules live here, and both are structural rather than a matter of
 * remembering:
 *
 * 1. `score_breakdown` never leaves the server. It is not omitted from a
 *    list of fields — it is never read by any method below.
 *
 * 2. A LOCKED CARD CARRIES NO PHOTO FIELD AT ALL. Not an empty one, not a
 *    placeholder, not a blurred path. `teaser()` never generates a signed
 *    URL, so there is nothing in the payload for devtools to recover.
 *
 * The reason rule 2 is absolute: a CSS blur over a real image means anyone
 * who opens devtools has the full-resolution photograph of a real person on
 * a marriage platform. The veil is a gradient panel drawn in CSS with no
 * <img> element behind it, because the image is not there to be found.
 *
 * If a partial preview is ever wanted, it must be a SEPARATE server-rendered
 * blurred file with its own path — never the original.
 */
class MatchPresenter
{
    /**
     * A locked introduction. Score, city, first name, rationale — the hook
     * at full strength, and nothing that costs money to see.
     */
    public function teaser(MatchRecord $match): array
    {
        $them = $match->matched;

        return [
            'id' => $match->id,
            'status' => MatchRecord::STATUS_TEASER,
            'firstName' => $them?->first_name,
            'age' => $them?->age(),
            'city' => $them?->city,
            'score' => $match->displayScore(),
            'rationale' => $match->compatibility_rationale,

            /*
             * Deliberately absent from this array: photos, lastName, bio,
             * interests, contact of any kind, and the score breakdown.
             *
             * Absent, not null. A null `photos` key would still tell the
             * browser the shape of what is being withheld, and would invite
             * the next developer to fill it in.
             */
        ];
    }

    /**
     * An opened introduction. Everything the member paid for.
     */
    public function full(MatchRecord $match): array
    {
        $them = $match->matched;

        return [
            'id' => $match->id,
            'status' => $match->status,
            'firstName' => $them?->first_name,
            'lastName' => $them?->last_name,
            'age' => $them?->age(),
            'city' => $them?->city,
            'country' => $them?->country,
            'score' => $match->displayScore(),
            'rationale' => $match->compatibility_rationale,
            'commonalities' => $match->commonalities ?? [],
            'bio' => $them?->bio,
            'occupation' => $them?->occupation,
            'education' => $them?->education,
            'interests' => $them?->interests ?? [],
            'languages' => $them?->languages ?? [],
            'photos' => $this->signedPhotos($them?->photos ?? collect()),
            'unlockedAt' => $match->unlocked_at?->format('j F Y'),

            /*
             * Still absent: score_breakdown, and anything derived from a date
             * of birth beyond the age the member volunteered.
             */
        ];
    }

    /**
     * One-hour signed URLs, generated only for a match that has been paid
     * for. Ownership is checked again at the photo route itself — a URL that
     * escapes into a chat log stops working within the hour, and never
     * worked for anyone else in the first place.
     *
     * @param  iterable<Photo>  $photos
     */
    private function signedPhotos(iterable $photos): array
    {
        $out = [];

        foreach ($photos as $photo) {
            $out[] = [
                'slot' => $photo->slot,
                'url' => URL::temporarySignedRoute('photos.show', now()->addHour(), ['photo' => $photo->id]),
            ];
        }

        return $out;
    }

    /**
     * Dispatches on status so no caller has to decide, and so a caller
     * cannot accidentally ask for the full payload of a locked match.
     */
    public function forCard(MatchRecord $match): array
    {
        return $match->isUnlocked() ? $this->full($match) : $this->teaser($match);
    }
}
