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

    /**
     * ⚠️ NOT A GATE. THE RECORD THAT SOMEBODY WAS PRESENT.
     *
     * The three gates above are permissions: may she enter the pool, may this
     * introduction be released, may contact move. This is the act itself —
     * a named person stood between two people and made the introduction.
     *
     * It exists because the scholar distinguished approving from being present,
     * and only a woman with an appointed contact needs staff to carry it.
     */
    public const GATE_INTRODUCTION_MADE = 'introduction_made';

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
     * ⚠️ THE PHOTOGRAPH RULE. IT ASKS TWO QUESTIONS, AND IT USED TO ASK ONE.
     *
     * ── What it asked before ──────────────────────────────────────────────
     *
     * `maySeePhotographs()`, with no argument: is this contact appointed? A
     * family contact got a yes and was handed whatever photographs the payload
     * was carrying. The name promised an answer about photographs while the
     * body answered a question about seats.
     *
     * ⚠️ THAT LEAKED, AND IT LEAKED THE EXACT THING THIS SYSTEM EXISTS TO
     * PREVENT. A man may name a trusted contact too. When he did, his father
     * or brother opened the dashboard and was shown the WOMAN'S photographs —
     * a woman he is not related to, has never met, and has no standing over.
     * Her pictures reached an unrelated man, by design, from the feature built
     * to stop precisely that.
     *
     * ── What it asks now ──────────────────────────────────────────────────
     *
     * Both halves, and the subject is not optional:
     *
     *   1. Is he a family contact rather than an appointed stranger?
     *   2. Are these photographs his to look at?
     *
     * His ward's, yes — he is her mahram and that is the entire role. A man's,
     * yes — a man looking at a man's photograph is unremarkable and he cannot
     * advise on a match he is not allowed to look at. A woman's, only if she
     * is his ward. Never otherwise, at any gate, at any stage.
     *
     * ⚠️ THE ARGUMENT IS REQUIRED ON PURPOSE. A no-argument version left in
     * place would go on being called, and the second question would go on
     * being unasked. There is no way to ask this without naming the subject.
     */
    public function maySeePhotographsOf(?Profile $subject): bool
    {
        // An appointed contact is a stranger to everybody, including his ward.
        if ($this->is_appointed || $subject === null) {
            return false;
        }

        // His own ward. The mahram relationship the whole role rests on.
        if ($subject->user_id === $this->member_id) {
            return true;
        }

        // Anybody else on the platform. Her photographs are not his to see.
        return ! $subject->photographsAreProtected();
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
