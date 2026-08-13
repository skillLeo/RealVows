<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use App\Support\WhatsAppTemplates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Compare the templates Meta has approved against the ones this codebase
 * believes it is sending.
 *
 * ⚠️ THIS EXISTS BECAUSE WhatsAppTemplates IS NOT THE EMISSION POINT, AND FOR
 * A WHOLE PHASE IT CLAIMED TO BE.
 *
 * That file's docblock said "exactly as submitted to Meta". It was not, and a
 * screenshot of the Meta dashboard is what proved it: the approved body of
 * `verification_rejected` reads "Hi {{1}}, your RealVows verification needs
 * attention. Reason: {{2}}. Please log in to update your profile." while our
 * file carries an entirely different sentence, a link and an opt-out line.
 *
 * Both are two-variable templates, so nothing errors and nothing ever would.
 * The member simply receives Meta's words. Every guard we have — the
 * marriage-lead reader, the forbidden-terms reader, the opt-out rule — reads
 * OUR bodies, which is to say it reads an intention rather than a message.
 * That is the failure class this project keeps meeting: a check that passes
 * because it is pointed at the wrong artefact.
 *
 * ⚠️ THE PARAMETER COUNT IS THE ONE THAT BREAKS THINGS, AND IT IS THE ONE
 * NOBODY LOOKS AT. Send two values to a one-variable template and Meta returns
 * error 132000 and the message is never delivered — for every member, silently,
 * until somebody reads a log. Send one to a two-variable template and it is the
 * same. This command answers that question directly.
 *
 * ⚠️ CATEGORY IS CHECKED TOO, BECAUSE META CHANGES IT WITHOUT ASKING. It has
 * already recategorised `someone_interested` and `introduction_unlocked` from
 * utility to marketing, which changes the price and, more importantly, means a
 * member who has opted out of marketing stops receiving them while continuing
 * to receive the other four.
 *
 * Read-only. It sends nothing and writes nothing.
 */
class CheckWhatsAppTemplates extends Command
{
    /** Pinned to match MetaCloudSender. A version skew here would report a fiction. */
    private const GRAPH_VERSION = 'v23.0';

    protected $signature = 'realvows:whatsapp-templates-check';

    protected $description = 'Compare the templates Meta has approved against the ones this code sends';

    public function handle(SettingsService $settings): int
    {
        $waba = trim((string) $settings->get('whatsapp', 'business_account_id', ''));
        $token = trim((string) $settings->get('whatsapp', 'access_token', ''));

        if ($waba === '' || $token === '') {
            $this->error('Set the business account ID and access token first.');
            $this->line('  Both live in /admin/settings under WhatsApp.');

            return self::FAILURE;
        }

        try {
            $response = Http::withToken($token)
                ->timeout(20)
                ->get(sprintf('https://graph.facebook.com/%s/%s/message_templates', self::GRAPH_VERSION, $waba), [
                    'fields' => 'name,language,status,category,components',
                    'limit' => 250,
                ]);
        } catch (Throwable $e) {
            $this->error('Could not reach Meta: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error('Meta refused the request: '.($response->json('error.message') ?? $response->status()));

            /*
             * The commonest cause by a distance, and the message Meta returns
             * for it names the token rather than the permission, which sends
             * people to the wrong page.
             */
            $this->line('  A token missing whatsapp_business_management reads exactly like a bad token.');

            return self::FAILURE;
        }

        $live = [];

        foreach ($response->json('data') ?? [] as $template) {
            $live[$template['name']] = $template;
        }

        $problems = 0;

        foreach (WhatsAppTemplates::TEMPLATES as $name => $ours) {
            $expected = count($ours['variables']);

            if (! isset($live[$name])) {
                $this->line(sprintf('  <fg=red>absent</>    %-24s Meta has no template by this name.', $name));
                $problems++;

                continue;
            }

            $theirs = $live[$name];
            $actual = self::countVariables($theirs);
            $status = $theirs['status'] ?? 'UNKNOWN';
            $notes = [];

            /*
             * ⚠️ THE COUNT COMES FROM THE APPROVED BODY, NOT FROM A FIELD.
             * Meta does not return a variable count, so it is derived by
             * reading the placeholders back out of the text it approved.
             */
            if ($actual !== $expected) {
                $notes[] = sprintf('sends %d, Meta expects %d — every send fails with 132000', $expected, $actual);
            }

            if ($status !== 'APPROVED') {
                $notes[] = 'status is '.$status;
            }

            $liveCategory = strtoupper((string) ($theirs['category'] ?? ''));

            if ($liveCategory !== '' && $liveCategory !== $ours['category']) {
                $notes[] = sprintf('category is %s, we record %s', $liveCategory, $ours['category']);
            }

            if ($notes === []) {
                $this->line(sprintf('  <fg=green>ok</>        %-24s %d variable(s), %s', $name, $actual, strtolower($liveCategory)));

                continue;
            }

            $problems++;
            $this->line(sprintf('  <fg=yellow>differs</>   %-24s %s', $name, implode('; ', $notes)));
        }

        /*
         * ⚠️ REPORTED, NOT FAILED. Meta seeds `hello_world` into every account
         * and the client may have templates for something this codebase knows
         * nothing about. An extra template is information, not a fault.
         */
        $extra = array_diff(array_keys($live), WhatsAppTemplates::names());

        if ($extra !== []) {
            $this->line('');
            $this->line('  Also on Meta, unused by this code: '.implode(', ', $extra));
        }

        $this->line('');

        if ($problems > 0) {
            $this->warn(sprintf('%d of %d templates disagree with Meta.', $problems, count(WhatsAppTemplates::TEMPLATES)));

            return self::FAILURE;
        }

        $this->info('Every template matches what Meta has approved.');

        return self::SUCCESS;
    }

    /**
     * The highest placeholder number in the approved body.
     *
     * ⚠️ THE HIGHEST, NOT THE COUNT OF MATCHES. A body using {{1}} twice has
     * one variable, and counting occurrences would report two — which would
     * raise a false alarm on exactly the templates a person is most likely to
     * write that way.
     *
     * @param  array<string, mixed>  $template
     */
    private static function countVariables(array $template): int
    {
        foreach ($template['components'] ?? [] as $component) {
            if (strtoupper((string) ($component['type'] ?? '')) !== 'BODY') {
                continue;
            }

            preg_match_all('/\{\{(\d+)\}\}/', (string) ($component['text'] ?? ''), $matches);

            return $matches[1] === [] ? 0 : max(array_map('intval', $matches[1]));
        }

        return 0;
    }
}
