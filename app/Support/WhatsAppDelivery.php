<?php

namespace App\Support;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * ⚠️ WHETHER THE PUBLIC PAGES MAY PROMISE WHATSAPP.
 *
 * The home page and the questions page have been saying "email and WhatsApp"
 * while every WhatsApp send fails at Meta on a billing setting. Nobody has
 * signed up yet so nobody has been misled, but the sentence is a promise on a
 * public page that the platform cannot keep, and the day the first member
 * arrives it becomes a false statement.
 *
 * ⚠️ THIS GOVERNS COPY, NOT SENDING. The sender is configured separately and
 * keeps its own credentials. This answers one question: what do we tell people
 * we will do?
 *
 * ⚠️ FAILS OFF, ON PURPOSE, AND IT IS THE OPPOSITE DIRECTION TO PHOTOGRAPHS.
 *
 * Unreadable settings elsewhere fail closed to protect a member. Here closed
 * means "do not claim the channel" — the same instinct, pointed at a promise
 * instead of a photograph. Being wrong quietly offers a member less than they
 * get. Being wrong the other way tells them a message is coming that never
 * arrives, and they wait for it.
 */
class WhatsAppDelivery
{
    public const GROUP = 'whatsapp';

    public const KEY = 'delivery_enabled';

    public function __construct(private readonly SettingsService $settings) {}

    public function isOn(): bool
    {
        try {
            return $this->settings->bool(self::GROUP, self::KEY, false);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp delivery flag unreadable, public pages will not mention it.', [
                'error' => $e->getMessage(),
                'consequence' => 'The site says introductions arrive by email. That is the safe '
                    .'direction: it under-promises rather than describing a channel we may not have.',
            ]);

            return false;
        }
    }
}
