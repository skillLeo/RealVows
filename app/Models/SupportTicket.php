<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    /**
     * ⚠️ THE TYPES A PERSON CAN CHOOSE, AND ONE OF THEM IS THE POINT.
     *
     * `guardian_unfair` is the only route a member has to challenge a decision
     * she cannot see, made by somebody she cannot overrule. Everything else
     * here is ordinary support. It is auto-suggested after five unexplained
     * declines and must never be buried under "something else".
     */
    public const TYPES = [
        'guardian_unfair' => 'My trusted contact is not responding fairly',
        'verification' => 'A question about verification',
        'account' => 'A problem with my account',
        'introduction' => 'A concern about an introduction',
        'billing' => 'A question about a payment',
        'other' => 'Something else',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
