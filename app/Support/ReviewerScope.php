<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which members a reviewer is allowed to see at all.
 *
 * ── ⚠️ WHY THIS IS A SCOPE AND NOT A PERMISSION ───────────────────────────
 *
 * Verification means a person looks at photographs of a member. For a large
 * part of this client's market, a man looking at a woman's photographs is the
 * reason she would not join in the first place. So review is separated by
 * gender: `reviewer_female` sees women, `reviewer_male` sees men.
 *
 * A permission cannot express that. Permissions answer "may this person review
 * verifications", and both reviewers may. The question here is "WHICH RECORDS
 * EXIST for this person", which is a query, and it has to be answered in the
 * query or not at all.
 *
 * ⚠️ ENFORCED AT THE QUERY LAYER, NEVER IN THE VIEW. A view that hides a
 * photograph still served it: the controller loaded the record, the route
 * accepted the id, and the image endpoint streamed the bytes. Hiding it in
 * Blade or Vue changes what is on the screen and nothing about what is
 * reachable by typing a URL. Every finder in this class starts from a
 * constrained builder so an out-of-scope record does not exist to be found.
 *
 * ⚠️ 404, NEVER 403. Consistent with the admin area generally: a 403 confirms
 * the record exists and that somebody is not allowed to see it, which is
 * itself a disclosure. A reviewer probing ids learns nothing from a 404.
 */
class ReviewerScope
{
    public const FEMALE = 'reviewer_female';

    public const MALE = 'reviewer_male';

    /**
     * The genders this user may review, or null for unrestricted.
     *
     * ── ⚠️ WHICH ROLES ARE UNRESTRICTED, AND WHY THAT IS NOT FAILING OPEN ──
     *
     * `super_admin` and `admin` are unrestricted. The two reviewer roles are
     * scoped. Anything else gets an empty array and sees nothing.
     *
     * The first version of this scoped `admin` to nothing as well, on the
     * reasoning that an account somebody forgot to assign should see nothing
     * rather than everything. That reasoning was sound and the premise was
     * wrong: `admin` is not an unassigned account. It is the general reviewer
     * role the client hires into, deliberately granted, and it has been the
     * verification reviewer since Phase 1. Scoping it to nothing did not make
     * the system safer, it made the existing queue empty for the person whose
     * job is the queue.
     *
     * There is no fail-open risk here, because a user with no staff role never
     * reaches this code: EnsureUserIsAdmin rejects them with a 404 first. The
     * empty array below is for a staff account holding some other permission,
     * which should not be reading photographs by default.
     *
     * @return list<string>|null
     */
    public static function gendersFor(User $user): ?array
    {
        if ($user->hasAnyRole(['super_admin', 'admin'])) {
            return null;
        }

        if ($user->hasRole(self::FEMALE)) {
            /*
             * Non-binary members are reviewed by the female reviewer. This is
             * a judgement rather than a fact, and it is written down here so
             * it is visible and arguable rather than implicit. The client can
             * change it; nobody should have to infer it from a WHERE clause.
             */
            return ['woman', 'non_binary'];
        }

        if ($user->hasRole(self::MALE)) {
            return ['man'];
        }

        // A staff account with no reviewer role. Sees no member photographs.
        return [];
    }

    /**
     * Constrain a query that has a `profile` relationship.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyToProfileRelation(Builder $query, User $user): Builder
    {
        $genders = self::gendersFor($user);

        if ($genders === null) {
            return $query;
        }

        return $query->whereHas('profile', fn (Builder $q) => $q->whereIn('gender', $genders));
    }

    /**
     * Constrain a query over profiles themselves.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyToProfiles(Builder $query, User $user): Builder
    {
        $genders = self::gendersFor($user);

        return $genders === null ? $query : $query->whereIn('gender', $genders);
    }

    /** Whether this user may see photographs of a member of this gender. */
    public static function maySee(User $user, ?string $gender): bool
    {
        $genders = self::gendersFor($user);

        if ($genders === null) {
            return true;
        }

        return $gender !== null && in_array($gender, $genders, true);
    }
}
