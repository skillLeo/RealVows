<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One attempt to buy credits.
 *
 * `amount_cents` and `credits_granted` are snapshots taken when the purchase
 * is created, never read back from settings. A receipt has to keep telling
 * the truth after the client changes his prices.
 */
class Purchase extends Model
{
    use HasFactory;

    public const STATUS_INITIATED = 'initiated';

    public const STATUS_PENDING_3DS = 'pending_3ds';

    public const STATUS_AUTHORISED = 'authorised';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_REFUNDED = 'refunded';

    /** Statuses that are still waiting on the gateway for an answer. */
    public const IN_FLIGHT = [self::STATUS_INITIATED, self::STATUS_PENDING_3DS, self::STATUS_AUTHORISED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'credits_granted' => 'integer',
            'raw_response' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function amountFormatted(): string
    {
        return '$'.number_format($this->amount_cents / 100, 2);
    }

    /** The member-facing reference. Short enough to read down a phone line. */
    public function reference(): string
    {
        return 'RV-'.strtoupper(substr((string) $this->order_identifier, 0, 8));
    }
}
