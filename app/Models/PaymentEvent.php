<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only log of gateway traffic. Nothing updates or deletes a row.
 *
 * ⚠️ Write through PaymentEvent::record() and nowhere else — it is the only
 * path that runs the redactor, and a payload that skipped the redactor could
 * put a card number in the database permanently.
 */
class PaymentEvent extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'created_at';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
