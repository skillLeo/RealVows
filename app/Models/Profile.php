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
    protected $hidden = ['sign', 'element'];

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
