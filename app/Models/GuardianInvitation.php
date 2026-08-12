<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

/**
 * A single-use link that lets one person create one account.
 *
 * ⚠️ THE TOKEN IS HASHED AND THE PLAINTEXT IS RETURNED ONCE.
 *
 * A token sitting in the database is a password to somebody else's account. A
 * database dump, a backup on a laptop, or a support person reading a row would
 * all hand over a live link into a guardian account that can approve who a
 * member meets. Hashed, none of those do anything.
 *
 * The consequence is that a lost invitation cannot be re-sent — it can only be
 * re-issued, which invalidates the first. That is the correct trade and it is
 * the reason `issue()` returns the plaintext exactly once.
 */
class GuardianInvitation extends Model
{
    /** Long enough to be unguessable, short enough to survive a WhatsApp line. */
    public const TOKEN_BYTES = 24;

    public const LIFETIME_DAYS = 14;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * ⚠️ RETURNS THE PLAINTEXT. The caller must send it and then forget it.
     *
     * @return array{invitation: self, token: string}
     */
    public static function issue(Guardian $guardian): array
    {
        // Any earlier invitation for this guardian stops working. Two live
        // links to one account is one more than anybody needs.
        self::where('guardian_id', $guardian->id)->whereNull('used_at')->delete();

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        $invitation = self::create([
            'guardian_id' => $guardian->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::LIFETIME_DAYS),
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * ⚠️ LOOKED UP BY HASH, NOT BY ID-PLUS-COMPARE.
     *
     * A route that takes an id and then compares a token invites the id to be
     * enumerated. The hash is the only identifier, so a wrong token finds
     * nothing rather than finding a record and being refused.
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function consume(): void
    {
        $this->forceFill(['used_at' => now()])->save();
    }
}
