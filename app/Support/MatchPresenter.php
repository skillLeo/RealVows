<?php

namespace App\Support;

use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Support\ProfileOptions;
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
    public function __construct(private readonly \App\Services\Matching\MatchInterest $interest) {}

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

        /*
         * ⚠️ ASKED ONCE, AND EVERY REVEAL BELOW HANGS OFF THIS ONE ANSWER.
         *
         * Not re-derived per field. Two separate checks are two chances to
         * disagree, and the one that disagrees in the wrong direction hands
         * out a phone number.
         */
        $mutual = $this->interest->isMutual($match);

        /*
         * ⚠️ STAMPED HERE, AT THE EMISSION POINT, AND NOT AT THE CALL SITES.
         *
         * This used to be the show controller's job, and the dashboard — which
         * calls this same method through forCard() — never did it. So a member
         * could read an introduction in full and the admin log would report it
         * as never viewed.
         *
         * Putting it at the call sites is what failed. There were two, one of
         * them remembered, and nothing made the other fail. Here it is
         * structural: this method IS the emission of the unlocked contents, so
         * emitting without recording is no longer expressible.
         *
         * ⚠️ A WRITE IN A PRESENTER IS A SMELL AND IT IS THE LESSER ONE. The
         * alternative is a rule somebody has to remember at every future call
         * site, and this project has been caught six times by exactly that.
         *
         * `firstView` is read from `opened_at`, NOT from this, so recording a
         * dashboard view can never suppress the arrival ceremony on the match
         * page. That coupling is why the original conflation was load-bearing.
         */
        if ($match->isUnlocked() && $match->viewed_at === null) {
            $match->forceFill(['viewed_at' => now()])->save();
        }

        return [
            'id' => $match->id,
            'status' => $match->status,
            'firstName' => $them?->first_name,
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
            'verified' => $them?->isVerified() ?? false,

            /*
             * ⚠️ THE CEREMONY RUNS ONCE.
             *
             * The count and the ring drawing themselves are an arrival, and an
             * arrival that happens every time somebody revisits a page is not
             * an arrival, it is a delay.
             *
             * ⚠️ READ FROM `opened_at`, NOT `viewed_at`. They used to be one
             * column, which meant the honest fix to the admin log — recording
             * that the dashboard shows the unlocked contents — would have
             * stamped the ceremony as already played and the ring would never
             * have animated for anybody. Two questions, two columns.
             */
            'firstView' => $match->opened_at === null,

            'sections' => $this->sections($them),

            /*
             * What this member may do about this introduction. The buttons and
             * the endpoint read the same predicate, so they cannot disagree.
             */
            'interest' => [
                'canRespond' => $this->interest->canRespond($match),
                'response' => $match->response,
                'mutual' => $mutual,
            ],

            /*
             * ⚠️ THE LAST NAME AND THE CONTACT DETAILS ARE SPREAD IN, NOT SET
             * TO NULL. THIS IS THE SAME RULE AS THE TEASER PHOTOS.
             *
             * Unlocking buys a profile, not a person's identity. A last name
             * plus a city is enough to find somebody, so it waits for the same
             * moment the phone number does: both people, independently, having
             * said yes.
             *
             * `null` would be the easy version and it is the wrong one — it
             * tells the browser the shape of what is being withheld and invites
             * the next developer to populate it. Absent means there is nothing
             * in the payload to recover and nothing obvious to fill in.
             *
             * ⚠️ ONE-SIDED INTEREST REVEALS NOTHING. If this member has said
             * yes and the other has not answered, `$mutual` is false and this
             * block does not run. That is the case worth testing, because it is
             * the one a relaxed guard would quietly get wrong.
             */
            ...($mutual ? [
                'lastName' => $them?->last_name,
                'contact' => $this->contact($them),
            ] : []),

            /*
             * Still absent: score_breakdown, and anything derived from a date
             * of birth beyond the age the member volunteered.
             */
        ];
    }

    /**
     * How to reach somebody, once both people have agreed to be reachable.
     *
     * ⚠️ THE ONLY METHOD IN THIS CLASS THAT TOUCHES A User RECORD. Everything
     * else presents a Profile, which by design holds nothing that could
     * contact anyone. Keeping the reach into `user` in one named method means
     * a search for "who can leak an email address" has exactly one answer.
     *
     * @return array<string, string>
     */
    private function contact(?Profile $them): array
    {
        $user = $them?->user;

        if (! $user) {
            return [];
        }

        return array_filter([
            'email' => $user->email,
            'phone' => $user->fullPhone(),
        ]);
    }

    /**
     * The profile proper, in the same order as the seven-step builder, so it
     * is immediately legible to anyone who has filled one in.
     *
     * Photos and Account are deliberately not sections — the photos are the
     * gallery at the top, and the account is nobody else's business.
     *
     * ⚠️ EMPTY-FIELD DISCIPLINE. A row with nothing under it is never
     * emitted, and a section with no rows is never emitted at all. A profile
     * with gaps must read as a person who chose not to answer, never as a
     * broken page.
     */
    private function sections(?Profile $them): array
    {
        if (! $them) {
            return [];
        }

        $sections = [
            'Personal' => [
                'Lives in' => $this->place($them),
                'Nationality' => $them->nationality,
                'Open to relocating' => $this->yesNo($them->willing_to_relocate),
            ],
            'Physical' => [
                'Height' => $them->height_cm ? $this->height($them->height_cm) : null,
                'Build' => ProfileOptions::BODY_TYPE[$them->body_type] ?? null,
                'Eyes' => $them->eye_colour,
                'Hair' => $them->hair_colour,
            ],
            'Lifestyle' => [
                'Work' => $them->occupation,
                'Industry' => $them->industry,
                'Education' => $them->education,
                'Faith' => $them->religion,
                'How much faith matters' => ProfileOptions::FAITH_IMPORTANCE[$them->faith_importance] ?? null,
                'Smoking' => ProfileOptions::FREQUENCY[$them->smoking] ?? null,
                'Drinking' => ProfileOptions::FREQUENCY[$them->drinking] ?? null,
                'Children' => ProfileOptions::HAS_CHILDREN[$them->has_children] ?? null,
                'Languages' => $this->list($them->languages),
                'Pets' => $this->pets($them->pets),
            ],
            'Values' => [
                'Hoping to marry' => ProfileOptions::MARRIAGE_TIMELINE[$them->marriage_timeline] ?? null,
                'Wants children' => ProfileOptions::WANTS_CHILDREN[$them->wants_children] ?? null,
                'What matters most' => $this->coreValues($them->core_values),
                'When there is a disagreement' => ProfileOptions::CONFLICT_STYLE[$them->conflict_style] ?? null,
                'Shows care by' => $this->loveLanguages($them->love_languages),
                'Interests' => $this->list($them->interests),
            ],
            'Preferences' => [
                'Looking for' => $this->ageRange($them->preferences),
                'How far they would look' => ProfileOptions::DISTANCE_PREFERENCE[$them->preferences?->distance_preference] ?? null,
                'Qualities they hope for' => $this->list($them->preferences?->preferred_qualities),
            ],
        ];

        // Drop empty rows, then drop sections left with nothing in them.
        $out = [];

        foreach ($sections as $heading => $rows) {
            $rows = array_filter($rows, fn ($v) => $v !== null && $v !== '');

            if ($rows !== []) {
                $out[] = ['heading' => $heading, 'rows' => $rows];
            }
        }

        return $out;
    }

    private function place(?Profile $them): ?string
    {
        return implode(', ', array_filter([$them?->city, $them?->country])) ?: null;
    }

    /** Centimetres with feet and inches alongside — the audience uses both. */
    private function height(int $cm): string
    {
        $inches = (int) round($cm / 2.54);

        return sprintf('%dcm (%d\'%d")', $cm, intdiv($inches, 12), $inches % 12);
    }

    private function yesNo(?bool $value): ?string
    {
        return $value === null ? null : ($value ? 'Yes' : 'No');
    }

    private function list(?array $values): ?string
    {
        $values = array_filter($values ?? [], fn ($v) => is_string($v) && trim($v) !== '');

        return $values === [] ? null : implode(', ', $values);
    }

    private function pets(?array $pets): ?string
    {
        $pets = array_filter($pets ?? []);

        if ($pets === [] || $pets === ['none']) {
            return $pets === ['none'] ? 'None' : null;
        }

        return implode(', ', array_map(fn ($p) => ProfileOptions::PETS[$p] ?? $p, $pets));
    }

    /** Ranked, and shown in rank order because the order is the meaning. */
    private function coreValues(?array $values): ?string
    {
        $values = array_filter($values ?? []);

        return $values === []
            ? null
            : implode(', ', array_map(fn ($v) => ProfileOptions::CORE_VALUES[$v] ?? $v, $values));
    }

    private function loveLanguages(?array $values): ?string
    {
        $values = array_filter($values ?? []);

        return $values === []
            ? null
            : implode(', ', array_map(fn ($v) => ProfileOptions::LOVE_LANGUAGES[$v] ?? $v, $values));
    }

    private function ageRange(?object $preferences): ?string
    {
        if (! $preferences?->age_min && ! $preferences?->age_max) {
            return null;
        }

        return "Someone {$preferences->age_min}–{$preferences->age_max}";
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
