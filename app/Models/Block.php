<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member's decision never to be shown another.
 *
 * ⚠️ SYMMETRIC IN EFFECT, ONE-SIDED TO DECLARE. See the migration: a block
 * that only hides the blocked from the blocker leaves the blocked party still
 * receiving the blocker, still able to express interest, and still able to
 * infer from the silence that something happened.
 */
class Block extends Model
{
    protected $guarded = ['id'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocker_profile_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'blocked_profile_id');
    }

    /** Is there a block between these two, in either direction? */
    public static function existsBetween(int $a, int $b): bool
    {
        return static::query()
            ->where(fn ($q) => $q->where('blocker_profile_id', $a)->where('blocked_profile_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_profile_id', $b)->where('blocked_profile_id', $a))
            ->exists();
    }
}
