<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The stand-in. Writes what it would have sent and reports success.
 *
 * ⚠️ IT IS BOUND WHENEVER THERE IS NO ACCESS TOKEN, WHICH INCLUDES THE REVIEW
 * BUILD THE CLIENT IS CLICKING THROUGH. So the whole interest-to-notification
 * flow is walkable with no Meta account at all, and — because this is
 * deterministic — what he sees is the shape of what the real one will do.
 *
 * ⚠️ IT MUST NEVER BE MISTAKEN FOR THE REAL ONE. This is the same trap the
 * payment gateway had: a settings screen that said "Meta" while the container
 * quietly handed every send to a stand-in. `mode()` answers 'stand-in' and the
 * admin banner reads it, so the question "is this actually sending?" has an
 * answer on screen rather than one that has to be inferred from what is
 * absent.
 *
 * ⚠️ THE NUMBER IS MASKED HERE TOO. The same reasoning as the webhook log:
 * storage/ sits inside the served directory on this host.
 */
class LogSender implements WhatsAppSender
{
    public function sendTemplate(string $to, string $template, array $variables): WhatsAppResult
    {
        Log::channel('whatsapp-webhook')->info('whatsapp send (stand-in)', [
            'to' => $this->mask($to),
            'template' => $template,
            'variables' => count($variables),
            'note' => 'Not sent. No access token configured, so the stand-in is bound.',
        ]);

        return WhatsAppResult::sent('stand-in-'.Str::random(16));
    }

    public function mode(): string
    {
        return 'stand-in';
    }

    private function mask(string $number): string
    {
        return strlen($number) <= 4
            ? str_repeat('•', strlen($number))
            : str_repeat('•', strlen($number) - 4).substr($number, -4);
    }
}
