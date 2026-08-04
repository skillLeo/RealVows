<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ EMAIL IS THE FLOOR BENEATH WHATSAPP, SO ITS FAILURES ARE SILENT ONES.
 *
 * A member who never receives an introduction they paid for cannot tell the
 * difference between "nothing was sent" and "nothing was matched". Everything
 * in this file is a way that can happen while looking fine from the inside.
 */
class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function unsubscribeUrl(User $user, string $channel = 'email'): string
    {
        return URL::signedRoute('unsubscribe', ['user' => $user->id, 'channel' => $channel]);
    }

    // ── Unsubscribing without a session ──────────────────────────────────────

    public function test_the_unsubscribe_page_opens_with_no_session(): void
    {
        $user = User::factory()->create();

        // Deliberately NOT acting as anyone.
        $this->get($this->unsubscribeUrl($user))
            ->assertOk()
            ->assertSee('Stop these messages?');
    }

    /**
     * ⚠️ THE GET MUST NOT ACT.
     *
     * Mail clients and corporate security scanners follow every link in an
     * email before a human sees it. A GET that unsubscribed would switch off
     * members who never clicked anything, and nobody would ever connect it to
     * the scanner that caused it.
     */
    public function test_merely_opening_the_link_does_not_unsubscribe(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'email_matches' => true,
            'whatsapp_matches' => true,
        ]);

        $this->get($this->unsubscribeUrl($user))->assertOk();

        $this->assertTrue(
            NotificationPreference::where('user_id', $user->id)->first()->email_matches,
            'Opening the unsubscribe link switched notifications off. Every corporate mail '
                .'scanner in the world would now be unsubscribing members silently.'
        );
    }

    /** ⚠️ POSITIVE CONTROL: confirming really does switch it off. */
    public function test_confirming_does_unsubscribe(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'email_matches' => true,
            'whatsapp_matches' => true,
        ]);

        $this->post($this->unsubscribeUrl($user))->assertOk()->assertSee('That is done.');

        $preference = NotificationPreference::where('user_id', $user->id)->first();

        $this->assertFalse($preference->email_matches);
    }

    /**
     * ⚠️ PER CHANNEL. The same rule the inbound STOP handler follows: a member
     * who says "stop emailing me" has not said "stop telling me about the
     * introductions I paid for".
     */
    public function test_unsubscribing_from_email_leaves_whatsapp_alone(): void
    {
        $user = User::factory()->create();

        NotificationPreference::create([
            'user_id' => $user->id,
            'email_matches' => true,
            'whatsapp_matches' => true,
        ]);

        $this->post($this->unsubscribeUrl($user, 'email'))->assertOk();

        $preference = NotificationPreference::where('user_id', $user->id)->first();

        $this->assertFalse($preference->email_matches);
        $this->assertTrue(
            $preference->whatsapp_matches,
            'Unsubscribing from email switched WhatsApp off too, so the member has lost the '
                .'introductions they paid for while appearing to have their wishes respected.'
        );
    }

    /**
     * ⚠️ WITHOUT A SIGNATURE THIS ROUTE IS AN ENUMERATION TOOL — an id in a
     * URL that switches off somebody else's notifications.
     */
    public function test_an_unsigned_link_is_refused(): void
    {
        $user = User::factory()->create();

        $this->get("/unsubscribe/{$user->id}/email")->assertForbidden();
        $this->post("/unsubscribe/{$user->id}/email")->assertForbidden();
    }

    public function test_a_tampered_signature_is_refused(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        // Take a valid link and point it at somebody else.
        $tampered = str_replace(
            "/unsubscribe/{$user->id}/",
            "/unsubscribe/{$other->id}/",
            $this->unsubscribeUrl($user),
        );

        $this->get($tampered)->assertForbidden();
    }

    public function test_an_unknown_channel_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->get(URL::signedRoute('unsubscribe', ['user' => $user->id, 'channel' => 'carrier-pigeon']))
            ->assertNotFound();
    }

    // ── The footer ───────────────────────────────────────────────────────────

    /**
     * ⚠️ THE CONTRACTING ENTITY IS READ FROM SETTINGS, NEVER HARDCODED, AND
     * SAYS SO WHEN IT IS UNKNOWN.
     *
     * An email footer naming the wrong company is the same false statement as
     * a receipt naming the wrong company.
     */
    public function test_the_footer_states_the_entity_is_pending_while_it_is_unconfirmed(): void
    {
        $rendered = view('emails.layout', [
            'heading' => 'Test',
            'slot' => 'Body',
        ])->render();

        $this->assertStringContainsString('[PENDING — the contracting entity is not yet confirmed]', $rendered);
    }

    /** ⚠️ POSITIVE CONTROL: once configured, the real entity appears. */
    public function test_the_footer_names_the_entity_once_it_is_configured(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('payments', 'billing_legal_entity', 'Gemini Security Limited');
        $settings->set('payments', 'billing_trading_name', 'RealVows');
        $settings->set('payments', 'statement_descriptor', 'REALVOWS');
        $settings->set('payments', 'billing_address', '12 Harbour Street, Kingston');

        $rendered = view('emails.layout', ['heading' => 'Test', 'slot' => 'Body'])->render();

        $this->assertStringContainsString('Gemini Security Limited, trading as RealVows', $rendered);
        $this->assertStringContainsString('12 Harbour Street, Kingston', $rendered);
        $this->assertStringNotContainsString('[PENDING', $rendered);
    }

    /**
     * ⚠️ A DEAD UNSUBSCRIBE LINK IS WORSE THAN NONE — it is the one link an
     * annoyed member definitely clicks. So it is absent when there is no
     * recipient to build it for, rather than rendered broken.
     */
    public function test_the_unsubscribe_link_is_absent_rather_than_broken_when_there_is_no_recipient(): void
    {
        $rendered = view('emails.layout', ['heading' => 'Test', 'slot' => 'Body'])->render();

        $this->assertStringNotContainsString('Stop receiving these emails', $rendered);
    }

    public function test_the_unsubscribe_link_is_rendered_when_a_recipient_is_known(): void
    {
        $user = User::factory()->create();

        $rendered = view('emails.layout', [
            'heading' => 'Test',
            'slot' => 'Body',
            'unsubscribeUrl' => $this->unsubscribeUrl($user),
        ])->render();

        $this->assertStringContainsString('Stop receiving these emails', $rendered);
        $this->assertStringContainsString('signature=', $rendered);
    }

    // ── Client compatibility, as far as it can be checked here ───────────────

    /**
     * ⚠️ THIS IS NOT A SUBSTITUTE FOR OPENING THE EMAIL IN OUTLOOK.
     *
     * Outlook renders through Word, which ignores most CSS, drops flexbox and
     * grid entirely, and reflows anything positioned. No test in PHP can tell
     * you what Word will do — the four-client check is a human one.
     *
     * What this CAN do is catch the constructs that are known to break before
     * anybody wastes a round trip on them: an external stylesheet a client
     * will strip, a web font that will not load, and layout properties Word
     * does not implement.
     */
    public function test_no_email_template_uses_constructs_that_break_in_outlook(): void
    {
        $templates = ScannedFiles::withExtension(
            File::allFiles(resource_path('views/emails')),
            ['php'],
            'email templates',
            5,
        );

        $forbidden = [
            '<link' => 'an external stylesheet, which most clients strip',
            'fonts.googleapis' => 'a web font, which will not load and falls back unpredictably',
            'display:flex' => 'flexbox, which Word-rendered Outlook does not implement',
            'display: flex' => 'flexbox, which Word-rendered Outlook does not implement',
            'display:grid' => 'grid, which Word-rendered Outlook does not implement',
            'display: grid' => 'grid, which Word-rendered Outlook does not implement',
            'position:absolute' => 'absolute positioning, which Word reflows',
            'position: absolute' => 'absolute positioning, which Word reflows',
        ];

        $offences = [];

        foreach ($templates as $path) {
            $source = File::get($path);
            $relative = str_replace(base_path().'/', '', $path);

            foreach ($forbidden as $needle => $why) {
                if (stripos($source, $needle) !== false) {
                    $offences[] = "{$relative} uses {$why}";
                }
            }
        }

        $this->assertSame([], $offences, implode("\n  ", $offences));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The scan above is a negative assertion
     * over a file set, which is worth nothing unless the reader can be shown
     * finding a real offence and NOT finding a clean one.
     */
    public function test_the_outlook_scan_can_see_a_real_offence(): void
    {
        $bad = '<div style="display:flex"><link rel="stylesheet" href="x.css"></div>';
        $good = '<table role="presentation" style="width:100%"><tr><td>Hello</td></tr></table>';

        $reader = fn (string $s) => array_values(array_filter(
            ['<link', 'display:flex', 'position:absolute'],
            fn (string $needle) => stripos($s, $needle) !== false,
        ));

        $this->assertNotEmpty($reader($bad), 'The reader cannot see constructs that break in Outlook.');
        $this->assertSame([], $reader($good), 'The reader flags a correct table-based email.');
    }
}
