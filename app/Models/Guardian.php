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

    /**
     * Whether the trusted contact's CURRENT answer at this gate is approval.
     *
     * ⚠️ THE LATEST DECISION, NOT ANY DECISION. THIS ASKED `exists()`.
     *
     * The decisions table is append-only on purpose — a changed mind writes a
     * second row, and the support flow that judges an unreasonable trusted
     * contact reads the whole sequence. That is the right shape for a log.
     *
     * But it means "has an approval ever been recorded" and "is this approved"
     * are different questions, and this method was asking the first while every
     * caller wanted the second. A contact who approved an introduction, thought
     * better of it and declined left the approval row sitting in the sequence,
     * so `exists()` kept returning true and the gate stayed open on the answer
     * they had explicitly withdrawn.
     *
     * A person cannot take back a yes. That is not a small bug on a platform
     * where the whole point of the role is to be able to say no.
     *
     * So the sequence is preserved and only its READING changes: the most
     * recent row wins, which is what changing your mind means.
     */
    public function hasApproved(string $gate, ?int $matchId = null): bool
    {
        $latest = $this->decisions()
            ->where('gate', $gate)
            ->when($matchId, fn ($q) => $q->where('match_id', $matchId))
            /*
             * ⚠️ id BREAKS THE TIE, AND IT HAS TO. decided_at is a second-
             * resolution timestamp, so approving and immediately declining can
             * produce two rows with the identical value — which is precisely
             * the case this method exists to get right. Ordering by id as well
             * makes the later insert the later decision, always.
             */
            ->orderByDesc('decided_at')
            ->orderByDesc('id')
            ->first();

        return $latest?->decision === GuardianDecision::APPROVED;
    }
}
