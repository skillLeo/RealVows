<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsService;
use App\Support\WhatsAppTemplates;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The check that reads Meta rather than reading our own file.
 *
 * ⚠️ EVERY CASE HERE IS A PAIR. A reader that reports drift on everything is
 * as useless as one that reports it on nothing, and this project has shipped
 * both. So each fault is proved twice: once with a payload that must trip it
 * and once with a payload that must not.
 */
class WhatsAppTemplateDriftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);

        $settings = app(SettingsService::class);
        $settings->set('whatsapp', 'business_account_id', '1553517519889000');
        $settings->set('whatsapp', 'access_token', 'test-token');
    }

    /**
     * A Meta payload that agrees with our file on every template.
     *
     * @param  array<string, array<string, mixed>>  $overrides  keyed by template name
     * @return array<string, mixed>
     */
    private function metaPayload(array $overrides = []): array
    {
        $data = [];

        foreach (WhatsAppTemplates::TEMPLATES as $name => $ours) {
            // Placeholders only. The wording is Meta's business, and the whole
            // point of this command is that the wording is not what we check.
            $body = 'Body text';

            for ($i = 1; $i <= count($ours['variables']); $i++) {
                $body .= " {{{$i}}}";
            }

            $data[] = array_merge([
                'name' => $name,
                'language' => 'en',
                'status' => 'APPROVED',
                'category' => $ours['category'],
                'components' => [['type' => 'BODY', 'text' => $body]],
            ], $overrides[$name] ?? []);
        }

        return ['data' => $data];
    }

    public function test_it_passes_when_meta_agrees_with_the_file(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->metaPayload())]);

        $this->artisan('realvows:whatsapp-templates-check')->assertSuccessful();
    }

    public function test_it_catches_a_template_that_sends_the_wrong_number_of_variables(): void
    {
        /*
         * ⚠️ THE FAULT THAT ACTUALLY HAPPENS. Somebody edits a template in the
         * dashboard, adds a placeholder, and every send of it begins failing
         * with error 132000 — for every member, with nothing on any screen to
         * say so.
         */
        Http::fake(['graph.facebook.com/*' => Http::response($this->metaPayload([
            'someone_interested' => [
                'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, {{2}} is interested.']],
            ],
        ]))]);

        $this->artisan('realvows:whatsapp-templates-check')
            ->expectsOutputToContain('someone_interested')
            ->assertFailed();
    }

    public function test_it_catches_a_template_meta_has_never_approved(): void
    {
        $payload = $this->metaPayload();

        $payload['data'] = array_values(array_filter(
            $payload['data'],
            fn (array $t) => $t['name'] !== 'phone_verification_code',
        ));

        Http::fake(['graph.facebook.com/*' => Http::response($payload)]);

        $this->artisan('realvows:whatsapp-templates-check')
            ->expectsOutputToContain('phone_verification_code')
            ->assertFailed();
    }

    public function test_it_catches_a_template_that_is_not_approved_yet(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response($this->metaPayload([
            'mutual_interest' => ['status' => 'PENDING'],
        ]))]);

        $this->artisan('realvows:whatsapp-templates-check')
            ->expectsOutputToContain('PENDING')
            ->assertFailed();
    }

    public function test_it_catches_a_category_meta_changed_underneath_us(): void
    {
        /*
         * Not hypothetical. Meta moved two of these from utility to marketing
         * on its own, which changed the price and means a member who opted out
         * of marketing silently stops receiving them.
         */
        Http::fake(['graph.facebook.com/*' => Http::response($this->metaPayload([
            'verification_approved' => ['category' => 'MARKETING'],
        ]))]);

        $this->artisan('realvows:whatsapp-templates-check')
            ->expectsOutputToContain('MARKETING')
            ->assertFailed();
    }

    public function test_it_counts_a_repeated_placeholder_once(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL FOR THE COUNTER ITSELF. Counting matches rather
         * than taking the highest number would report two variables here and
         * raise drift on a template that is perfectly correct — a false alarm
         * on exactly the wording a person is most likely to write.
         */
        Http::fake(['graph.facebook.com/*' => Http::response($this->metaPayload([
            'someone_interested' => [
                'components' => [['type' => 'BODY', 'text' => 'Hi {{1}}, someone likes you, {{1}}.']],
            ],
        ]))]);

        $this->artisan('realvows:whatsapp-templates-check')->assertSuccessful();
    }

    public function test_it_reports_extra_templates_without_failing(): void
    {
        $payload = $this->metaPayload();
        $payload['data'][] = [
            'name' => 'hello_world',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'category' => 'UTILITY',
            'components' => [['type' => 'BODY', 'text' => 'Welcome and congratulations!']],
        ];

        Http::fake(['graph.facebook.com/*' => Http::response($payload)]);

        $this->artisan('realvows:whatsapp-templates-check')
            ->expectsOutputToContain('hello_world')
            ->assertSuccessful();
    }

    /**
     * ⚠️ THE PROPERTY IS "IT MUST NOT CLAIM THEY MATCH", NOT "IT MUST EXIT
     * NON-ZERO".
     *
     * This asserted a failure exit code, which was right while the command was
     * only ever run by hand. It is now scheduled weekly, and a red line every
     * Monday for a blocker everybody already knows about — Meta business
     * verification, which is the client's to complete and may take weeks — is
     * precisely how a check trains people to skip it. Then the week it means
     * something, it looks like all the others.
     *
     * So the exit code relaxed and the real guarantee tightened: it makes no
     * request, it says plainly that it could not compare, and it never reports
     * agreement it did not verify. Guessing was always the thing being
     * forbidden.
     */
    public function test_it_refuses_to_guess_when_there_are_no_credentials(): void
    {
        app(SettingsService::class)->set('whatsapp', 'access_token', '');

        Http::fake();

        $this->artisan('realvows:whatsapp-templates-check')
            ->expectsOutputToContain('not configured yet, so there is nothing to compare')
            ->assertSuccessful();

        // It cannot have learned anything, because it asked nobody.
        Http::assertNothingSent();
    }

    /**
     * ⚠️ AND THE CONTROL: silence while unconfigured must not become silence
     * when there IS drift. Relaxing an exit code is one step from removing the
     * alarm, and the two states have to be distinguishable.
     */
    public function test_it_still_fails_loudly_when_a_template_has_actually_drifted(): void
    {
        $payload = $this->metaPayload();
        $payload['data'][0]['status'] = 'REJECTED';

        Http::fake(['graph.facebook.com/*' => Http::response($payload)]);

        $this->artisan('realvows:whatsapp-templates-check')->assertFailed();
    }
}
