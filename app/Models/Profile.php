<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Profile extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * ⚠️ `sign` and `element` are the internal mechanism behind the
     * compatibility score. They are hidden here so that any accidental
     * `toArray()`, `toJson()`, or Inertia prop containing a whole Profile
     * silently drops them instead of leaking them to the browser.
     *
     * This is the safety net, not the plan: controllers should be selecting
     * explicit columns anyway. But a safety net is what catches the case
     * nobody thought about.
     */
    /*
     * ⚠️ WAS ['sign', 'element'], HIDING THE AFFINITY INPUTS. Both columns are
     * dropped and the component is deleted, so there is nothing left to hide
     * here. Kept as an empty list rather than removed, because a model with no
     * $hidden reads as a model nobody thought about.
     */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'willing_to_relocate' => 'boolean',
            'has_tattoos' => 'boolean',
            'has_piercings' => 'boolean',
            'pets' => 'array',
            'languages' => 'array',
            'core_values' => 'array',
            'love_languages' => 'array',
            'interests' => 'array',
            'personality_scales' => 'array',
            'submitted_at' => 'datetime',
            'body_type_is_dealbreaker' => 'boolean',
            'last_matched_at' => 'datetime',
        ];
    }

    /**
     * The first name, or null if there is not really one.
     *
     * ⚠️ A WHITESPACE-ONLY NAME IS TRUTHY IN PHP, AND EVERY GREETING IN THE
     * PRODUCT IS A TERNARY ON TRUTHINESS.
     *
     * `$firstName ? "Hello $firstName," : 'Hello,'` looks safe and is not:
     * a profile whose first name is "   " — which a copy-paste, an autofill,
     * or a trimmed-to-nothing form field all produce — takes the first branch
     * and renders "Hello    ,".
     *
     * The empty-string case is handled by the same ternary and the null case
     * by the fallback, so this is the only gap, and it is invisible until a
     * real person receives it. Normalising here means every surface gets it
     * right without each one remembering to trim.
     */
    public function greetingName(): ?string
    {
        $name = trim((string) $this->first_name);

        return $name === '' ? null : $name;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(ProfilePreference::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function verification(): HasOne
    {
        return $this->hasOne(Verification::class)->latestOfMany();
    }

    /** Introductions the engine chose FOR this person. Never a browse surface. */
    public function matchesAsSeeker(): HasMany
    {
        return $this->hasMany(MatchRecord::class, 'seeker_profile_id');
    }

    /** Introductions where this person was the one chosen. Used for "no prior pass". */
    public function matchesAsCandidate(): HasMany
    {
        return $this->hasMany(MatchRecord::class, 'matched_profile_id');
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }

    public function isVerified(): bool
    {
        return $this->verification?->status === Verification::STATUS_VERIFIED;
    }

    /**
     * ⚠️ WHOSE PHOTOGRAPHS THE MODESTY RULE IS ABOUT.
     *
     * The same set GuardianPolicy already uses to decide who must have a
     * trusted contact at all, named once so the two rules cannot drift apart.
     * If a gender is ever added to the enum, it is protected here until
     * somebody decides otherwise, which is the safe direction to be wrong in.
     */
    public const PROTECTED_GENDERS = ['woman', 'non_binary'];

    /**
     * ⚠️ FAILS CLOSED ON A NULL GENDER. The column is nullable and an
     * incomplete profile has no answer yet. "Not a woman" and "we do not know"
     * are different facts, and only one of them is safe to act on.
     */
    public function photographsAreProtected(): bool
    {
        return $this->gender === null
            || in_array($this->gender, self::PROTECTED_GENDERS, true);
    }

    /**
     * The matching pool. Verification gates everything: an unverified profile
     * can never be matched, matched to, or shown to anyone. Enforced here at
     * the query level so no controller can forget it.
     */
    public function scopeMatchable($query)
    {
        return $query
            ->whereNotNull('submitted_at')
            ->whereHas('verification', fn ($q) => $q->where('status', Verification::STATUS_VERIFIED))
            ->whereHas('user', fn ($q) => $q
                ->where('is_active', true)

                /*
                 * ⚠️ EMAIL VERIFICATION IS NOW A GATE ON THE POOL, NOT ON THE
                 * PRODUCT, AND THIS LINE IS WHAT MAKES THAT SAFE.
                 *
                 * It used to be enforced by middleware on the profile builder:
                 * an unverified member could not build a profile at all, so
                 * they could never reach a pool. The client is right that this
                 * loses people — being sent to an inbox before you have built
                 * anything is where somebody stops — so the builder is now open
                 * to them.
                 *
                 * ⚠️ THAT MOVED THE FLOOR OUT FROM UNDER THE OLD GUARANTEE. The
                 * check above is IDENTITY verification, the reviewed photograph
                 * and code. It has never known anything about email. Without
                 * this line, an unverified member could complete a profile, be
                 * approved by a reviewer, and be matched — reachable for the
                 * first time the moment the middleware came off.
                 *
                 * ⚠️ IN THE QUERY, NEVER IN THE PRESENTER. An unverified member
                 * is ABSENT from the candidate set rather than filtered out of
                 * its results. Same rule as the trusted-contact gate, same
                 * reason: a filter is one forgotten call site from a leak, and
                 * a row that was never selected cannot leak at all.
                 */
                ->whereNotNull('email_verified_at'));
    }
}
