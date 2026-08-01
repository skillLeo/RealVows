<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    public const STATUS_OPEN = 'open';

    public const REASONS = [
        'misrepresented_identity' => 'They are not who they say they are',
        'inappropriate_content' => 'Something in their profile is inappropriate',
        'appears_to_be_scam' => 'This looks like a scam',
        'other' => 'Something else',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'reporter_profile_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Profile::class, 'subject_profile_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}
