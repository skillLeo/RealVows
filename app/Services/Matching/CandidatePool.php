<?php

namespace App\Services\Matching;

use App\Models\MatchRecord;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who is even eligible, decided in SQL before a single score is calculated.
 *
 * Eliminating first is not an optimisation, it is the design. Scoring
 * somebody who is going to be filtered out anyway wastes the budget the
 * whole batch has to fit inside on shared hosting — and worse, a filter
 * applied after scoring is a filter somebody can forget to apply.
 *
 * ⚠️ Verification is enforced HERE, in the query, not in a view. An
 * unverified profile can never enter a pool, never be scored, and never be
 * shown to anybody, and no controller can undo that by forgetting a scope.
 */
class CandidatePool
{
    /**
     * @return Collection<int, Profile>
     */
    public function for(Profile $seeker): Collection
    {
        return $this->query($seeker)->get();
    }

    public function query(Profile $seeker): Builder
    {
        $query = Profile::query()
            ->with('preferences')
            ->matchable()                            // verified, submitted, active
            ->where('profiles.id', '!=', $seeker->id); // 1. not self

        $this->mutualGender($query, $seeker);        // 2.
        $this->bothAdults($query);                   // 3.
        $this->location($query, $seeker);            // 6.
        $this->children($query, $seeker);            // 7.
        $this->dealBreakers($query, $seeker);        // 8. and 9.
        $this->noPriorPass($query, $seeker);         // 10.
        $this->noExistingRow($query, $seeker);       // 11.
        $this->guardianVerified($query);             // 12.
        $this->notBlocked($query, $seeker);          // 12.

        return $query;
    }

    /**
     * ⚠️ BLOCKING IS A HARD FILTER, NOT A DISPLAY RULE.
     *
     * Filtering blocked people out at display time would mean the engine still
     * scored them, still wrote a match row, and — on the paid product — still
     * consumed one of the introductions a member is entitled to, on somebody
     * they can never be shown. The member would simply receive fewer
     * introductions than they paid for, with nothing anywhere saying why.
     *
     * ⚠️ IT LOOKS BOTH WAYS ROUND. A block hides the blocked from the blocker
     * AND the blocker from the blocked. One-directional, the blocked party
     * keeps receiving the blocker as an introduction and can express interest
     * in them — and the silence that follows is itself a disclosure. On a
     * platform this small, in a country this small, that is how a block turns
     * into an escalation.
     */
    /**
     * ⚠️ NOBODY ENTERS THE POOL WITHOUT THE TRUSTED CONTACT THEIR POLICY LEVEL
     * REQUIRES.
     *
     * This is the first B5 gate, applied where the other hard filters are: in
     * the query, so an ungated member does not exist to be matched rather than
     * being matched and hidden afterwards.
     *
     * ⚠️ IT FILTERS THE CANDIDATE, NOT THE SEEKER. The seeker's own gate is
     * asked before generation runs at all — a member who should not be matched
     * should not be having a pool built for them. This side stops somebody
     * being introduced TO a person who has not cleared their own requirement,
     * which is the direction a reader naturally forgets.
     *
     * ⚠️ NO-OP WHEN THE REQUIREMENT IS OFF, and that is the configured state
     * today. `whereIn` on an unconstrained set would be a filter that silently
     * empties the pool for everybody, so the level is checked first and the
     * clause is not added at all when nothing is required.
     */
    private function guardianVerified(Builder $query): void
    {
        $policy = app(\App\Support\GuardianPolicy::class);

        if ($policy->isOff() || $policy->level() === \App\Support\GuardianPolicy::OPTIONAL) {
            return;
        }

        $gendersNeedingOne = $policy->level() === \App\Support\GuardianPolicy::REQUIRED_ALL
            ? array_keys(\App\Support\ProfileOptions::GENDER)
            : ['woman', 'non_binary'];

        $query->where(function (Builder $q) use ($gendersNeedingOne) {
            $q->whereNotIn('profiles.gender', $gendersNeedingOne)
                ->orWhereExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('guardians')
                        ->whereColumn('guardians.member_id', 'profiles.user_id')
                        ->where('guardians.status', \App\Models\Guardian::STATUS_VERIFIED);
                });
        });
    }

    private function notBlocked(Builder $query, Profile $seeker): void
    {
        $query->whereNotExists(function ($q) use ($seeker) {
            $q->selectRaw('1')
                ->from('blocks')
                ->where(function ($either) use ($seeker) {
                    $either->where(function ($w) use ($seeker) {
                        $w->where('blocker_profile_id', $seeker->id)
                            ->whereColumn('blocked_profile_id', 'profiles.id');
                    })->orWhere(function ($w) use ($seeker) {
                        $w->whereColumn('blocker_profile_id', 'profiles.id')
                            ->where('blocked_profile_id', $seeker->id);
                    });
                });
        });
    }

    /**
     * Both people must be looking for what the other one is. A seeker with
     * nothing stated matches nobody rather than everybody — an unanswered
     * question is not consent to be shown to the whole platform.
     */
    private function mutualGender(Builder $query, Profile $seeker): void
    {
        if (! $seeker->gender || ! $seeker->seeking) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('gender', $seeker->seeking)
            ->where('seeking', $seeker->gender);
    }

    /**
     * Belt and braces over the 18+ gate in SaveStepRequest. That gate stops
     * a date of birth being saved; this stops one that somehow exists from
     * ever reaching a pool.
     */
    private function bothAdults(Builder $query): void
    {
        $query->whereNotNull('date_of_birth')
            ->whereDate('date_of_birth', '<=', now()->subYears(18)->toDateString());
    }

    private function location(Builder $query, Profile $seeker): void
    {
        $preference = $seeker->preferences?->distance_preference;

        if (! $preference || $preference === 'anywhere') {
            return;
        }

        // Willingness to relocate satisfies any distance preference — the
        // candidate has already answered the question being asked.
        $query->where(function (Builder $q) use ($seeker, $preference) {
            $q->where('willing_to_relocate', true);

            if ($preference === 'same_city' && $seeker->city) {
                $q->orWhereRaw('LOWER(TRIM(city)) = ?', [mb_strtolower(trim($seeker->city))]);
            } elseif ($preference === 'same_country' && $seeker->country) {
                $q->orWhereRaw('LOWER(TRIM(country)) = ?', [mb_strtolower(trim($seeker->country))]);
            } else {
                $q->orWhereRaw('1 = 1');
            }
        });
    }

    /**
     * The one genuine irreconcilable. Somebody who definitely wants children
     * and somebody who definitely does not should never be introduced,
     * however well everything else scores.
     *
     * 'open' and 'undecided' are not positions, so they never eliminate.
     */
    private function children(Builder $query, Profile $seeker): void
    {
        if ($seeker->wants_children === 'yes') {
            $query->where(fn (Builder $q) => $q->whereNull('wants_children')->orWhere('wants_children', '!=', 'no'));
        }

        if ($seeker->wants_children === 'no') {
            $query->where(fn (Builder $q) => $q->whereNull('wants_children')->orWhere('wants_children', '!=', 'yes'));
        }
    }

    /**
     * Deal-breakers ELIMINATE. They are the one place in the product where a
     * member's answer removes people outright rather than moving a number,
     * which is exactly what the profile builder promises them.
     */
    private function dealBreakers(Builder $query, Profile $seeker): void
    {
        $breakers = $seeker->preferences?->deal_breakers ?? [];

        foreach ($breakers as $breaker) {
            match ($breaker) {
                'smoking' => $query->where(fn (Builder $q) => $q->whereNull('smoking')->orWhere('smoking', 'never')),
                'drinking' => $query->where(fn (Builder $q) => $q->whereNull('drinking')->orWhere('drinking', 'never')),
                'has_children' => $query->where(fn (Builder $q) => $q->whereNull('has_children')->orWhere('has_children', 'none')),
                'no_children_wanted' => $query->where(fn (Builder $q) => $q->whereNull('wants_children')->orWhere('wants_children', '!=', 'no')),
                'different_faith' => $seeker->religion
                    ? $query->whereRaw('LOWER(TRIM(religion)) = ?', [mb_strtolower(trim($seeker->religion))])
                    : null,
                'long_distance' => $seeker->city
                    ? $query->where(fn (Builder $q) => $q
                        ->whereRaw('LOWER(TRIM(city)) = ?', [mb_strtolower(trim($seeker->city))])
                        ->orWhere('willing_to_relocate', true))
                    : null,
                default => null,
            };
        }

        /*
         * Body type as a deal-breaker is opt-in and off by default, because
         * removing people outright should always be a decision somebody made
         * on purpose rather than a default they inherited.
         */
        $wanted = $seeker->preferences?->body_types ?? [];

        if ($seeker->body_type_is_dealbreaker && $wanted !== []) {
            $query->whereIn('body_type', $wanted);
        }
    }

    /**
     * A pass is final in both directions. Somebody who said no should not
     * reappear, and somebody who was passed on should not be shown the
     * person who passed.
     */
    private function noPriorPass(Builder $query, Profile $seeker): void
    {
        $query->whereNotExists(function ($q) use ($seeker) {
            $q->selectRaw(1)
                ->from('matches')
                ->where('response', MatchRecord::RESPONSE_PASSED)
                ->where(function ($inner) use ($seeker) {
                    $inner->where(function ($w) use ($seeker) {
                        $w->where('seeker_profile_id', $seeker->id)
                            ->whereColumn('matched_profile_id', 'profiles.id');
                    })->orWhere(function ($w) use ($seeker) {
                        $w->whereColumn('seeker_profile_id', 'profiles.id')
                            ->where('matched_profile_id', $seeker->id);
                    });
                });
        });
    }

    private function noExistingRow(Builder $query, Profile $seeker): void
    {
        $query->whereNotExists(function ($q) use ($seeker) {
            $q->selectRaw(1)
                ->from('matches')
                ->where('seeker_profile_id', $seeker->id)
                ->whereColumn('matched_profile_id', 'profiles.id');
        });
    }
}
