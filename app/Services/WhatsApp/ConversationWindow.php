<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Meta's 24-hour customer service window.
 *
 * ⚠️ THE WINDOW IS SHUT ALMOST ALL THE TIME, AND THAT IS THE NORMAL STATE.
 *
 * It opens only when the MEMBER messages us, and stays open for 24 hours from
 * their last message. Most members never message us at all, so for most of
 * this product's sending, only an approved template is permitted.
 *
 * That is why WhatsAppSender has no sendText() and why every notification is
 * a template. This class exists so the rule is checked BEFORE a send rather
 * than discovered at the API as error 131047 — by which point the member is
 * already waiting for a message that will not arrive.
 */
class ConversationWindow
{
    /** Meta's number, not ours. It is not configurable and not negotiable. */
    public const HOURS = 24;

    public function isOpen(User $user): bool
    {
        $last = $user->whatsapp_last_inbound_at;

        return $last !== null && $last->diffInHours(now()) < self::HOURS;
    }

    /**
     * May we send free text to this member right now?
     *
     * ⚠️ THE REFUSAL IS LOGGED AT WARNING, BECAUSE A GUARD THAT SKIPS SILENTLY
     * IS INDISTINGUISHABLE FROM ONE THAT NEVER FIRES.
     *
     * The honest reading of a refusal here is "somebody wrote code that tries
     * to send free text", which is a design mistake rather than a runtime
     * condition — every member-facing message in this product is a template.
     * So it is worth a line that says so rather than a quiet false.
     */
    public function allowsFreeForm(User $user, string $because = 'unspecified'): bool
    {
        if ($this->isOpen($user)) {
            return true;
        }

        Log::channel('whatsapp-webhook')->warning('free-form WhatsApp refused: window closed', [
            'user' => $user->id,
            'purpose' => $because,
            'last_inbound' => $user->whatsapp_last_inbound_at?->toIso8601String(),
            'rule' => 'Outside '.self::HOURS.' hours of the member\'s own message, only an approved template may be sent.',
            'fix' => 'Send an approved template instead. See App\Support\WhatsAppTemplates.',
        ]);

        return false;
    }

    /** The member has just messaged us, so the window opens or extends. */
    public function open(User $user): void
    {
        $user->forceFill(['whatsapp_last_inbound_at' => now()])->save();
    }
}
