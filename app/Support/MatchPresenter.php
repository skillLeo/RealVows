<?php

namespace App\Support;

use App\Models\MatchRecord;
use App\Models\Photo;
use App\Models\Profile;
use App\Services\Guardian\GuardianGate;
use App\Services\Matching\MatchInterest;
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
    public function __construct(
        private readonly MatchInterest $interest,
        private readonly PhotoVisibilityPolicy $photos,
    ) {}

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
            /*
             * ⚠️ PHOTOGRAPHS DO NOT GO TO THE MEMBER. THEY GO TO THE TRUSTED
             * CONTACT, WHO LOOKS AND ADVISES.
             *
             * This is the client's rule and it is a religious one: nobody here
             * is on display to the person they are introduced to. The pictures
             * exist so a member can be verified as real and so the person
             * responsible for them can look properly — not so two strangers
             * can appraise each other.
             *
             * ⚠️ THE KEY IS ABSENT, NOT EMPTY, AND THAT IS THE WHOLE DESIGN.
             *
             * An empty `photos` array still says "there are photographs and
             * you got none of them", and every template that iterates it would
             * silently render nothing while looking correct. Omitting the key
             * means a component that reaches for it fails loudly in
             * development instead of quietly in production — the same reason
             * GuardianPresenter ADDS photographs for a family contact rather
             * than removing them for an appointed one.
             *
             * ⚠️ AND IT IS DECIDED HERE, at the one place a member's payload is
             * built. A rule enforced in a Vue component is a rule about the
             * drawing, not about the data: the JSON would still carry signed
             * URLs to every photograph, one devtools panel away.
             */
            ...$this->photographs($match),
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
            /*
             * ⚠️ THE THIRD B5 GATE, ASKED AT THE EMISSION POINT.
             *
             * Mutual interest is necessary and no longer sufficient. Where the
             * policy requires a trusted contact, they approve before any
             * contact detail moves — that is the whole reason the gates are
             * three rather than one.
             *
             * Asked here rather than in the controller because this is the one
             * method that assembles the payload. A controller check would leave
             * every other caller of full() free to leak, and there is already
             * more than one.
             *
             * ⚠️ THE LAST NAME GOES WITH IT. A surname plus a city is enough to
             * find somebody, so releasing it while withholding the phone number
             * would be a distinction without a difference.
             */
            /*
             * ⚠️ THE FOURTH CONDITION, AND IT IS THE CLIENT'S SCHOLAR'S RULING.
             *
             * He was asked whether a guardian approving an exchange is the same
             * as a guardian being present for it. It is not, except where the
             * guardian is unjust, is not Muslim, or has died. Outside those, the
             * details go to HIM and he makes the introduction himself.
             *
             * So approval is necessary and still not sufficient. A member with
             * a family trusted contact never receives a phone number here, no
             * matter how many gates have opened, because the platform is no
             * longer the channel. See GuardianGate::contactIsMediated().
             */
            ...($mutual && $this->contactReleased($match) && $this->mediation($match) === GuardianGate::MEDIATION_NONE ? [
                'lastName' => $them?->last_name,
                'contact' => $this->contact($them),
            ] : []),

            /*
             * ⚠️ SAID PLAINLY, BECAUSE THE ALTERNATIVE READS AS A BROKEN PAGE.
             *
             * A member who has said yes, been approved three times, and then
             * finds no phone number would reasonably conclude the product had
             * failed. Every gate in this system that withholds something also
             * says why, and this is the last and most consequential one.
             */
            /*
             * ⚠️ THE SCHOLAR FORBADE THE PLATFORM ARRANGING THE CONVERSATION,
             * NOT THE INTRODUCTION ITSELF.
             *
             * His words: "arranging conversation between the two is not
             * permitted in Shariah. Whatever necessary matters need to be
             * satisfied should be handled through the women of the household."
             *
             * A trusted contact speaking to the man is not "the two" speaking.
             * That is permitted, and it is how a rishta has always worked. So
             * nothing here is a dead end — somebody is carrying it, and the
             * member is told who.
             */
            ...($mutual && $this->contactReleased($match) && $this->mediation($match) !== GuardianGate::MEDIATION_NONE ? [
                'introductionIsBeingMade' => true,
                'introductionIsBeingMadeBy' => $this->mediation($match),
                'introductionIsBeingMadeReason' => $this->mediation($match) === GuardianGate::MEDIATION_STAFF
                    ? 'RealVows is making this introduction for you. You will not receive their '
                        .'details yourself, and somebody here is handling it.'
                    : 'Your trusted contact is making this introduction for you. You will not '
                        .'receive their details yourself. Speak to him about how you would like '
                        .'to go from here.',
            ] : []),

            /*
             * Still absent: score_breakdown, and anything derived from a date
             * of birth beyond the age the member volunteered.
             */
        ];
    }

    /**
     * ⚠️ NO-OP WHEN NOTHING IS REQUIRED, which is the configured state today.
     *
     * Written as "is it required, and if so has it been approved" rather than
     * "has it been approved", because the second form withholds contact details
     * from every member on a platform where the requirement is off — a feature
     * nobody switched on silently breaking a feature everybody uses.
     */
    /**
     * ⚠️ BOTH SIDES, AND ASKING ONLY THE SEEKER WAS A LEAK IN THE OTHER
     * DIRECTION.
     *
     * A match is two rows, one per person. This used to ask only whether the
     * seeker of THIS row had cleared their own gate — so on the man's row it
     * asked about the man, who is not required to have a trusted contact at
     * all, and answered yes the moment interest was mutual.
     *
     * The result: he received her surname, email and phone BEFORE her trusted
     * contact had approved the contact gate, and whether or not the man was
     * ever meant to hold them. Her guardian's decision is recorded against HER
     * row, so nothing on his row ever consulted it.
     *
     * ⚠️ HER GUARDIAN'S DECISION LIVES ON HER ROW, WHICH IS WHY THE RECIPROCAL
     * LOOKUP IS HERE RATHER THAN A SECOND CALL WITH THE SAME MATCH.
     */
    private function contactReleased(MatchRecord $match): bool
    {
        $seeker = $match->seeker?->user;
        $other = $match->matched?->user;

        if (! $seeker || ! $other) {
            return false;
        }

        $gate = app(GuardianGate::class);

        if (! $gate->mayExchangeContact($seeker, $match)) {
            return false;
        }

        $reciprocal = app(\App\Services\Matching\MatchInterest::class)->reciprocal($match);

        /*
         * ⚠️ NO RECIPROCAL ROW MEANS NO RELEASE. It cannot be mutual without
         * one, and defaulting to "released" here would hand over details on the
         * strength of a row that does not exist.
         */
        if ($reciprocal === null) {
            return false;
        }

        return $gate->mayExchangeContact($other, $reciprocal);
    }

    /**
     * ⚠️ MEDIATION IS A PROPERTY OF THE PAIR, NOT OF WHOEVER IS LOOKING.
     *
     * If either person is mediated, then NEITHER receives the other's details —
     * otherwise the rule holds on her screen and is defeated on his, and the
     * two are talking anyway. The seeker's own mediation is checked first
     * because it decides what they are told, not merely whether.
     */
    private function mediation(MatchRecord $match): string
    {
        $gate = app(GuardianGate::class);

        $seeker = $match->seeker?->user;
        $other = $match->matched?->user;

        $mine = $seeker ? $gate->mediationFor($seeker) : GuardianGate::MEDIATION_NONE;

        if ($mine !== GuardianGate::MEDIATION_NONE) {
            return $mine;
        }

        return $other ? $gate->mediationFor($other) : GuardianGate::MEDIATION_NONE;
    }

    /**
     * ⚠️ ASKED OF THE SEEKER, WHO IS ALWAYS THE VIEWER OF THIS PAYLOAD. A
     * member only ever holds the seeker side of their own rows, so there is no
     * other person this could be about.
     */
    /**
     * ⚠️ RETIRED. Was single-sided and asked only about the seeker of this row,
     * which is what let the man receive her details. `mediation()` replaces it
     * and asks about the pair. Kept as a named absence rather than deleted so
     * that anybody following an old call site finds this note.
     */

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
                'The pace they want' => ProfileOptions::MARRIAGE_TIMELINE[$them->marriage_timeline] ?? null,
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

    /**
     * ⚠️ PHOTOGRAPHS ARE ADDED WHEN PERMITTED, NEVER REMOVED WHEN NOT.
     *
     * The direction is the safety property, and it is the same one
     * GuardianPresenter uses. "Unset the photos unless allowed" fails open: a
     * key added later by somebody who has not read this arrives in the payload
     * for everybody, and the withheld case leaks by default. This way a new
     * photograph key that is not put inside the permitted branch simply reaches
     * nobody, which is the safe direction to be wrong in.
     *
     * ⚠️ AND WHEN WITHHELD, THE KEY IS ABSENT RATHER THAN EMPTY. An empty
     * `photos` array still says "there are photographs and you got none of
     * them", and every template that iterates it renders nothing while looking
     * correct. Omitting it means a component that reaches for it fails loudly
     * in development instead of quietly in production.
     *
     * @return array<string, mixed>
     */
    private function photographs(MatchRecord $match): array
    {
        if (! $this->photos->mayMemberSee($match)) {
            return [
                'photographsWithheld' => true,
                'photographsWithheldReason' => $this->photos->explanation(),
            ];
        }

        $them = $match->matched;

        return [
            'photos' => ($them?->photos ?? collect())
                ->map(fn (Photo $photo) => [
                    'id' => $photo->id,
                    'url' => URL::temporarySignedRoute(
                        'photos.show',
                        now()->addHour(),
                        ['photo' => $photo->id],
                    ),
                ])
                ->values()
                ->all(),
        ];
    }
}
