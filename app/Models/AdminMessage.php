<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a thread between staff and one person.
 *
 * ⚠️ ONE COUNTERPARTY. A thread has staff on one side and exactly one member or
 * trusted contact on the other. There is no shape here that can hold a
 * conversation between two members.
 */
class AdminMessage extends Model
{
    /**
     * ⚠️ THE SENTENCE THAT SITS ABOVE THE COMPOSE BOX.
     *
     * Kept on the model rather than typed into a template, so the interface and
     * any future channel say the same thing, and so a search for the rule finds
     * the rule rather than a paragraph of markup.
     */
    public const RELAY_PROHIBITION =
        'Never pass a message between two members. If they want to speak, they exchange contact '
        .'details themselves once both have said yes.';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['from_staff' => 'boolean', 'read_at' => 'datetime'];
    }

    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counterparty_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
