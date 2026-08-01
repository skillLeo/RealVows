<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ⚠️ Phase 2 model. The table exists so its idempotency guarantee is set
 * while it is still empty; the granting and reserving logic is not built yet.
 *
 * Append-only by design: nothing in this application updates or deletes a
 * ledger row. A correction is a new row with an opposing delta, so the
 * history of a member's credits stays readable end to end.
 */
class CreditLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'credit_ledger';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
