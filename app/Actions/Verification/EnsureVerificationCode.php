<?php

namespace App\Actions\Verification;

use App\Models\Profile;
use App\Models\Verification;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Str;

class EnsureVerificationCode
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Every profile has exactly one open verification record, and it holds
     * the code the member writes on their note.
     *
     * Created the moment they reach the photos step, so the code is on screen
     * while they are still holding their phone — not generated afterwards,
     * when they would have to come back and take the photograph again.
     */
    public function handle(Profile $profile): Verification
    {
        $existing = $profile->verification;

        if ($existing && $existing->status !== Verification::STATUS_VERIFIED) {
            return $existing;
        }

        if ($existing) {
            return $existing;
        }

        return $profile->verification()->create([
            'status' => Verification::STATUS_PENDING,
            'code' => $this->makeCode(),
            'attempt_number' => 1,
        ]);
    }

    public function regenerate(Verification $verification): Verification
    {
        $verification->update(['code' => $this->makeCode()]);

        return $verification;
    }

    /**
     * Prefixed and uppercase so it is unmistakable in handwriting, and drawn
     * from an alphabet with no I, O, 0 or 1 — a reviewer comparing a written
     * note against a stored code should never have to decide whether that
     * character is a one or an ell.
     */
    private function makeCode(): string
    {
        $length = max(4, min(10, $this->settings->int('verification', 'code_length', 6)));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'RV-'.$code;
    }
}
