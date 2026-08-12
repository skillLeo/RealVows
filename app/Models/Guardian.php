<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A member's trusted contact.
 *
 * ⚠️ `is_appointed` IS THE MOST CONSEQUENTIAL COLUMN IN THIS MODEL. Read
 * GuardianPresenter before touching anything that serialises a guardian.
 */
class Guardian extends Model
{
    use HasFactory;

    public const STATUS_INVITED = 'invited';

    public const STATUS_REGISTERED = 'registered';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_REPLACED = 'replaced';

    /** The relationships a member may declare. `appointed` is set by staff. */
    public const RELATIONSHIPS = [
        'father' => 'Father',
        'brother' => 'Brother',
        'paternal_grandfather' => 'Grandfather',
        'paternal_uncle' => 'Uncle',
        'other_male_relative' => 'Another relative',
        'appointed' => 'Appointed by RealVows',
    ];

    /** The three points a trusted contact is asked to approve. */
    public const GATE_POOL = 'pool_entry';

    public const GATE_INTRODUCTION = 'introduction';

    public const GATE_CONTACT = 'contact_exchange';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_appointed' => 'boolean',
            'risk_reasons' => 'array',
            'verified_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            // ⚠️ Without this cast the column comes back as a string and every
            // ->format() call on it is a 500. Added after exactly that.
            'joint_photo_uploaded_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(GuardianDecision::class);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    /**
     * ⚠️ THE ONE QUESTION THE PHOTOGRAPH RULE TURNS ON.
     *
     * A family guardian is a mahram and may be shown the other party's
     * photographs, because that is the role he is performing. An appointed
     * guardian is a stranger the platform placed in that seat because the
     * member had nobody to name, and showing him photographs of a woman he has
     * no relationship to is precisely the harm this system exists to prevent.
     *
     * Expressed as a method rather than read as a column at each call site, so
     * there is one place to change it and one place to test.
     */
    public function maySeePhotographs(): bool
    {
        return ! $this->is_appointed;
    }

    /** Whether a decision has been recorded at this gate for this match. */
    public function hasApproved(string $gate, ?int $matchId = null): bool
    {
        return $this->decisions()
            ->where('gate', $gate)
            ->when($matchId, fn ($q) => $q->where('match_id', $matchId))
            ->where('decision', GuardianDecision::APPROVED)
            ->exists();
    }
}
