<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Setting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_secret' => 'boolean',
            'options' => 'array',
        ];
    }

    /**
     * Secrets are encrypted at rest. Decryption is deliberately forgiving:
     * if APP_KEY is ever rotated the old ciphertext becomes unreadable, and
     * returning null lets the settings screen show an empty field the client
     * can simply retype — far better than a 500 that locks him out of the
     * page where he would fix it.
     */
    public function plainValue(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if (! $this->is_secret) {
            return $this->value;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (Throwable) {
            return null;
        }
    }

    public function setPlainValue(?string $plain): void
    {
        if ($plain === null || $plain === '') {
            $this->value = null;

            return;
        }

        $this->value = $this->is_secret ? Crypt::encryptString($plain) : $plain;
    }

    /**
     * What the browser is allowed to see for a secret: the last four
     * characters only, so the client can recognise which key is stored
     * without the value ever leaving the server in full.
     */
    public function maskedValue(): ?string
    {
        $plain = $this->plainValue();

        if ($plain === null || $plain === '') {
            return null;
        }

        if (! $this->is_secret) {
            return $plain;
        }

        return str_repeat('•', 8).substr($plain, -4);
    }
}
