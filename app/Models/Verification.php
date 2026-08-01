<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verification extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVIEW = 'review';

    /**
     * The selfie is never rendered on a profile and never shown to another
     * member — only to a reviewing admin. Hiding the paths keeps it out of
     * any serialised model that reaches a member's browser.
     */
    protected $hidden = ['selfie_path', 'selfie_webp_path'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'attempt_number' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REVIEW], true);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}
