<?php

namespace App\Services\WhatsApp;

/**
 * Sending one WhatsApp template message.
 *
 * ⚠️ TEMPLATE, NOT TEXT, AND THAT IS META'S RULE RATHER THAN A PREFERENCE.
 *
 * Outside a 24-hour window from the member's own last message to us, only an
 * APPROVED template may be sent. Every message this product sends is outside
 * that window by definition — a member is told about a match hours or days
 * after they last touched anything — so free text is not an option we are
 * choosing against. It is not available.
 *
 * That is why this contract takes a template name and an ordered list of
 * variables rather than a message body. There is deliberately no sendText().
 */
interface WhatsAppSender
{
    /**
     * @param  string  $to  E.164 without punctuation, e.g. 18765550199
     * @param  list<string>  $variables  Positional, matching {{1}}, {{2}} …
     */
    public function sendTemplate(string $to, string $template, array $variables): WhatsAppResult;

    /** 'live' or 'stand-in', for the admin banner. */
    public function mode(): string;
}
