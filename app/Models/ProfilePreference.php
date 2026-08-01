<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePreference extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'body_types' => 'array',
            'deal_breakers' => 'array',
            'preferred_qualities' => 'array',
            'age_min' => 'integer',
            'age_max' => 'integer',
            'height_min_cm' => 'integer',
            'height_max_cm' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
