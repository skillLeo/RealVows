<?php

namespace App\Jobs;

use App\Models\MatchRecord;
use App\Models\User;
use App\Services\Matching\MatchInterest;
use App\Services\WhatsApp\WhatsAppSender;
use App\Support\WhatsAppTemplates;
use App\Services\Settings\SettingsService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Both people said yes. Tell both people.
 *
 * ⚠️ THE ONLY JOB IN THE PRODUCT THAT CAUSES A PHONE NUMBER TO BE REVEALED,
 * SO IT RE-ASKS THE QUESTION RATHER THAN TRUSTING ITS OWN ARGUMENT.
 *
 * It is dispatched with a match id and nothing else. By the time it runs —
 * which on shared hosting is up to a minute later — the world may have moved:
 * a profile may have been deleted, a member suspended, a report upheld. So it
 * reloads and re-checks mutuality instead of believing the dispatcher.
 *
 * ⚠️ UNIQUE PER PAIR, NOT PER ROW.
 *
 * A→B and B→A are two rows describing one event. Keyed by row id, a race
 * where both people answer within the same minute would announce the same
 * mutual match twice — two messages each, for one thing that happened once.
 * The key is the two profile ids, sorted, so both rows resolve to it.
 */
class AnnounceMutualInterest implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public function __construct(public int $matchId) {}

    public function uniqueId(): string
    {
        $match = MatchRecord::find($this->matchId);

        if (! $match) {
            return 'missing-'.$this->matchId;
        }

        $pair = [$match->seeker_profile_id, $match->matched_profile_id];
        sort($pair);

        return 'mutual-'.implode('-', $pair);
    }

    public function handle(MatchInterest $interest, WhatsAppSender $sender, SettingsService $settings): void
    {
        $match = MatchRecord::with(['seeker.user', 'matched.user'])->find($this->matchId);

        if (! $match) {
            return;
        }

        /*
         * ⚠️ RE-CHECKED, NOT ASSUMED. If this is no longer mutual — because a
         * profile went away, or because the dispatch was wrong — then nobody
         * is told anything. Silence is always the safe failure here.
         */
        if (! $interest->isMutual($match)) {
            Log::channel('whatsapp-webhook')->info('mutual announcement skipped', [
                'match' => $match->id,
                'reason' => 'no longer mutual at run time',
            ]);

            return;
        }

        $template = trim((string) $settings->get('whatsapp', 'template_name', ''));

        /*
         * ⚠️ EACH PERSON IS TOLD THE OTHER'S NAME. The two entries are
         * deliberately mirror images, and getting them the wrong way round
         * tells every member that they themselves are interested in them.
         */
        foreach ([
            [$match->seeker?->user, $match->seeker?->first_name, $match->matched?->first_name],
            [$match->matched?->user, $match->matched?->first_name, $match->seeker?->first_name],
        ] as [$recipient, $theirOwnName, $otherName]) {
            $this->tell($recipient, $theirOwnName, $otherName, $sender, $template);
        }
    }

    /**
     * ⚠️ ONE MEMBER'S FAILURE MUST NOT SWALLOW THE OTHER'S MESSAGE.
     *
     * Without this, a bad number on one side throws, the job retries, and the
     * person whose number was fine gets the same message up to three times
     * while the other still gets none.
     */
    private function tell(?User $recipient, ?string $theirOwnName, ?string $otherName, WhatsAppSender $sender, string $template): void
    {
        if (! $recipient || ! $otherName || $template === '') {
            return;
        }

        // Their own setting. A match notification is not a receipt — it is
        // something they may switch off, and switching it off must work.
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
         * resolves position from the declaration submitted to Meta. A swap
         * here would send successfully and tell somebody their own name is the
         * person who liked them back.
         */
        $result = $sender->sendTemplate($number, $template, WhatsAppTemplates::order('mutual_interest', [
            'first_name' => (string) ($theirOwnName ?? 'there'),
            'other_first_name' => $otherName,
        ]));

        if ($result->sent) {
            return;
        }

        Log::channel('whatsapp-webhook')->warning('mutual announcement failed', [
            'user' => $recipient->id,
            'retryable' => $result->retryable,
            'code' => $result->code,
            'error' => $result->error,
        ]);

        /*
         * ⚠️ ONLY A RETRYABLE FAILURE THROWS. A bad number or an unapproved
         * template will fail identically forever, and each attempt is billed
         * and counts against the quality rating Meta uses to set our sending
         * limits. Retrying those turns one mistyped number into a throttled
         * account.
         */
        if ($result->retryable) {
            throw new \RuntimeException('WhatsApp send failed and is retryable: '.$result->error);
        }
    }
}
