<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Services\WhatsApp\WhatsAppSender;
use App\Support\WhatsAppTemplates;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Somebody you were introduced to is interested in you.
 *
 * ⚠️ THE PAYLOAD IS A RECIPIENT ID AND NOTHING ELSE, DELIBERATELY.
 *
 * A queued job is a row in the `jobs` table, readable by anyone with database
 * access and visible on an admin queue screen. Carrying the admirer's id or
 * the match id would make "who is interested in whom" reconstructable from a
 * queue entry — before the recipient has answered, and possibly before they
 * ever do.
 *
 * One-sided interest is the other member's private state. It buys the
 * recipient a nudge to come back and look, and nothing else.
 *
 * ⚠️ NOT UNIQUE, AND THAT IS ON PURPOSE. Two different people may express
 * interest in the same member on the same day and both are worth telling them
 * about. Uniqueness here would silently swallow the second.
 */
class AnnounceSomeoneInterested implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** 1 minute, 5, 15 — then it stops rather than nagging Meta. */
    public array $backoff = [60, 300, 900];

    public function __construct(public int $recipientId) {}

    public function handle(WhatsAppSender $sender, SettingsService $settings): void
    {
        $recipient = User::with('notificationPreference')->find($this->recipientId);

        if (! $recipient) {
            return;
        }

        /*
         * ⚠️ THEIR SETTING, AND SWITCHING IT OFF MUST ACTUALLY WORK. A match
         * notification is not a receipt — it is not something they are owed
         * regardless of preference.
         */
        if (! ($recipient->notificationPreference?->whatsapp_matches ?? true)) {
            return;
        }

        $number = preg_replace('/\D+/', '', (string) $recipient->fullPhone());

        if (! $number) {
            return;
        }

        /*
         * ⚠️ KEYED, NOT ORDERED. The call site never states a variable order,
         * so it cannot state one incorrectly — WhatsAppTemplates::order()
         * resolves position from the declaration that was submitted to Meta.
         */
        $result = $sender->sendTemplate(
            $number,
            'someone_interested',
            WhatsAppTemplates::order('someone_interested', [
                'first_name' => (string) ($recipient->profile?->first_name ?? 'there'),
            ]),
        );

        if ($result->sent) {
            return;
        }

        Log::channel('whatsapp-webhook')->warning('someone_interested send failed', [
            'user' => $recipient->id,
            'retryable' => $result->retryable,
            'code' => $result->code,
            'error' => $result->error,
        ]);

        // Only a transient failure is worth repeating — see WhatsAppResult.
        if ($result->retryable) {
            throw new \RuntimeException('WhatsApp send failed and is retryable: '.$result->error);
        }
    }
}
