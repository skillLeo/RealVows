<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One direction of one introduction.
 *
 * Named MatchRecord because `match` is a PHP keyword and `class Match` will
 * not parse. The table is `matches`.
 *
 * ⚠️ `score_breakdown` NEVER REACHES A MEMBER. It is the six component
 * scores and the weight redistribution, and it is the one place the internal
 * mechanism is legible. It is visible on /admin/matches and nowhere else.
 * $hidden below is the safety net; the plan is that no member-facing
 * serialiser ever reads it. See MatchPresenter, which is the only thing
 * permitted to build a member payload from this model.
 */
class MatchRecord extends Model
{
    use HasFactory;

    public const STATUS_TEASER = 'teaser';

    public const STATUS_UNLOCKED = 'unlocked';

    public const STATUS_EXPIRED = 'expired';

    public const RESPONSE_INTERESTED = 'interested';

    public const RESPONSE_PASSED = 'passed';

    protected $table = 'matches';

    protected $guarded = ['id'];

    protected $hidden = ['score_breakdown'];

    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'score_breakdown' => 'array',
            'commonalities' => 'array',
            'generated_at' => 'datetime',
            'unlocked_at' => 'datetime',
            'delivered_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function seeker(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'seeker_profile_id');
    }

    public function matched(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'matched_profile_id');
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(CreditLedgerEntry::class, 'unlocked_by_ledger_id');
    }

    public function isUnlocked(): bool
    {
        return $this->status === self::STATUS_UNLOCKED;
    }

    /** The score a member sees. Whole numbers — precision is for the audit log. */
    public function displayScore(): int
    {
        return (int) round((float) $this->total_score);
    }

    public function scopeTeaser($query)
    {
        return $query->where('status', self::STATUS_TEASER);
    }

    public function scopeUnlocked($query)
    {
        return $query->where('status', self::STATUS_UNLOCKED);
    }

    /**
     * What a member is entitled to see, in the order they should see it.
     *
     * Unlocked first — they paid for those — then teasers by score. Never a
     * browse surface: this only ever returns rows the engine wrote for this
     * one seeker.
     */
    public function scopeForSeeker($query, int $profileId)
    {
        return $query->where('seeker_profile_id', $profileId)
            ->where('status', '!=', self::STATUS_EXPIRED)
            ->orderByRaw("CASE WHEN status = 'unlocked' THEN 0 ELSE 1 END")
            ->orderByDesc('total_score')
            ->orderBy('id');
    }
}
