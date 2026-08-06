<?php

namespace App\Support;

use App\Models\Verification;

/**
 * The founding offer: the first hundred verified members are introduced at no
 * cost, permanently.
 *
 * ⚠️ THIS IS A CLAIM WITH AN EXPIRY, AND THAT IS WHY IT IS A CLASS RATHER THAN
 * A SENTENCE.
 *
 * The client asked for "be one of the first 100 people to create your account
 * and get matched for free". Typed into a template, that sentence is true until
 * the hundred and first person joins and then it is a lie on the home page, and
 * nothing in the product would know. A marketing page must never make a promise
 * the database contradicts.
 *
 * So the number is counted, and the page asks.
 *
 * ── ⚠️ WHAT IS COUNTED, AND WHY IT IS VERIFICATIONS ───────────────────────
 *
 * The offer is about being INTRODUCED free, and nobody is introduced before a
 * real person has verified them. Counting sign-ups instead would let a hundred
 * abandoned registrations close an offer that nobody actually took, and the
 * client would have given away nothing while the page stopped saying so.
 *
 * ── ⚠️ THE PUBLIC PAGES NEVER RUN THIS QUERY ──────────────────────────────
 *
 * Same rule as FreePeriod: the marketing pages touch no database. The count is
 * projected into the PublicState file by the scheduler and read from there.
 * This class is what the scheduler calls.
 */
class FoundingOffer
{
    /** The client's number. */
    public const PLACES = 100;

    /**
     * How many of the hundred places are still open, counted now.
     *
     * ⚠️ NEVER NEGATIVE. At 143 verified members this returns 0, not -43, so a
     * template cannot accidentally render "minus forty-three places left".
     */
    public function remaining(): int
    {
        $taken = Verification::query()
            ->where('status', Verification::STATUS_VERIFIED)
            ->count();

        return max(0, self::PLACES - $taken);
    }
}
