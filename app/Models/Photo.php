<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Photo extends Model
{
    protected $guarded = ['id'];

    /**
     * Exactly four slots, in the order a member fills them, with the labels
     * and guidance they see beside each one.
     */
    public const SLOTS = [
        'passport' => [
            'title' => 'Passport style',
            'help' => 'Clear face, front facing',
        ],
        'full_length' => [
            'title' => 'Full length',
            'help' => 'Any setting, however you like',
        ],
        'social' => [
            'title' => 'Social',
            'help' => 'With friends or family',
        ],
        'open' => [
            'title' => 'Your choice',
            'help' => 'Anything that shows who you are',
        ],
    ];

    /**
     * Storage paths are never exposed. A photo is read only through a signed,
     * expiring URL behind an authorisation check, so `path` must not travel
     * to the browser in a serialised model.
     */
    protected $hidden = ['path', 'webp_path', 'thumbnail_path'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function title(): string
    {
        return self::SLOTS[$this->slot]['title'] ?? $this->slot;
    }
}
