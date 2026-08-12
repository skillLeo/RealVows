<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One decision, at one gate, at one moment.
 *
 * ⚠️ APPEND-ONLY. Nothing updates a row here and nothing deletes one. A
 * guardian who changes their mind produces a second row, and the support flow
 * that judges whether somebody is behaving unreasonably reads the whole
 * sequence. A mutable log cannot answer that question honestly, and this is
 * the record that decides whether a member is allowed to meet somebody.
 */
class GuardianDecision extends Model
{
    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchRecord::class, 'match_id');
    }
}
