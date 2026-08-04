<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to reach one member on one channel.
 *
 * ⚠️ THE STATUSES ARE A ONE-WAY LADDER AND MUST NOT GO BACKWARDS.
 *
 * Meta's callbacks are not ordered. A `delivered` can arrive after a `read`
 * when a phone reconnects and flushes a queue — so applying whatever arrived
 * last would demote a message the member has already opened, and the admin
 * panel would show a message going backwards.
 */
class MessageDelivery extends Model
{
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const CHANNEL_EMAIL = 'email';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_READ = 'read';

    public const STATUS_FAILED = 'failed';

    /**
     * How far along each status is. `failed` sits at the top on purpose: it is
     * terminal, and nothing Meta sends afterwards may quietly un-fail a
     * message that has already triggered an email fallback.
     */
    private const RANK = [
        self::STATUS_QUEUED => 0,
        self::STATUS_SENT => 1,
        self::STATUS_DELIVERED => 2,
        self::STATUS_READ => 3,
        self::STATUS_FAILED => 4,
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'retryable' => 'boolean',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'fell_back_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(MatchRecord::class, 'match_id');
    }

    /**
     * Apply a status from a provider callback, refusing to go backwards.
     *
     * Returns whether anything changed, so a caller can avoid re-triggering
     * work on a repeated callback.
     */
    public function applyStatus(string $status, ?\DateTimeInterface $at = null): bool
    {
        if (! isset(self::RANK[$status])) {
            return false;
        }

        if (self::RANK[$status] <= self::RANK[$this->status]) {
            return false;
        }

        $column = match ($status) {
            self::STATUS_SENT => 'sent_at',
            self::STATUS_DELIVERED => 'delivered_at',
            self::STATUS_READ => 'read_at',
            self::STATUS_FAILED => 'failed_at',
            default => null,
        };

        $this->status = $status;

        if ($column) {
            $this->{$column} = $at ?? now();
        }

        $this->save();

        return true;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /** A failed WhatsApp message that email has not yet answered. */
    public function needsFallback(): bool
    {
        return $this->hasFailed()
            && $this->channel === self::CHANNEL_WHATSAPP
            && $this->fell_back_at === null;
    }
}
