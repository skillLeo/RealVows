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
     * The matching pool. Verification gates everything: an unverified profile
     * can never be matched, matched to, or shown to anyone. Enforced here at
     * the query level so no controller can forget it.
     */
    public function scopeMatchable($query)
    {
        return $query
            ->whereNotNull('submitted_at')
            ->whereHas('verification', fn ($q) => $q->where('status', Verification::STATUS_VERIFIED))
            ->whereHas('user', fn ($q) => $q->where('is_active', true));
    }
}
