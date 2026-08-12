<?php

namespace Tests\Feature;

use App\Mail\IntroductionDelivered;
use App\Mail\MutualInterest;
use App\Mail\SomeoneInterested;
use App\Models\Profile;
use App\Models\User;
use App\Support\WhatsAppTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ NOTHING SENT TO A MEMBER MAY NAME A HOST THAT IS NOT PRODUCTION.
 *
 * ── Why this exists ───────────────────────────────────────────────────────
 *
 * Six approved WhatsApp templates carried `realvows.skillleo.com` — the
 * developer's own staging box — for weeks after the client bought his own
 * server and domain. Nothing failed. Nothing looked wrong. The site was live
 * and correct at realvows.com the whole time.
 *
 * The bug was invisible precisely because it only appears at the last moment:
 * the day Meta approves those templates and delivery switches on, real members
 * receive a link to somebody else's server. By then the message has been sent
 * and cannot be recalled.
 *
 * ⚠️ THIS IS A DIFFERENT QUESTION FROM OutboundLinkTest.
 *
 * That test asks "is this host one we control", and the staging box passes it,
 * because we do control it. This asks the stricter question: "is this the host
 * a MEMBER should be sent to". Staging is ours and is still the wrong answer.
 * Both tests are needed and neither subsumes the other.
 */
class ProductionHostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ EXACTLY ONE HOST, AND NO STAGING ENTRY.
     *
     * The temptation when this fails is to add the staging host here so the
     * build goes green. That is the bug, written down as a permission. If a
     * surface needs to differ between environments it reads config, and if it
     * cannot read config it names production.
     */
    private const PRODUCTION_HOSTS = ['realvows.com', 'www.realvows.com'];

    /** Hosts that are ours but must never reach a member. */
    private const NEVER_TO_A_MEMBER = [
        'realvows.skillleo.com',
        'srv1896577.hstgr.cloud',
        'localhost',
        '127.0.0.1',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    public function test_no_whatsapp_template_sends_a_member_to_a_non_production_host(): void
    {
        $offences = [];

        foreach (WhatsAppTemplates::TEMPLATES as $name => $template) {
            foreach ($this->strayHosts($template['body']) as $host) {
                $offences[] = "whatsapp:{$name} sends members to {$host}";
            }
        }

        $this->assertSame([], $offences, $this->explain($offences));
    }

    /**
     * ⚠️ RENDERED, NOT READ OFF DISK. A mailable builds its links from
     * `route()` and `config('app.url')`, so the template source can be
     * perfectly clean while the delivered message carries a staging host.
     */
    public function test_no_rendered_email_sends_a_member_to_a_non_production_host(): void
    {
        config(['app.url' => 'https://realvows.com']);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://realvows.com');

        $recipient = User::factory()->create(['email' => 'aisha@example.com']);
        Profile::factory()->woman()->create(['user_id' => $recipient->id, 'first_name' => 'Aisha']);

        $offences = [];

        foreach ($this->mailables($recipient) as $label => $mailable) {
            $rendered = $mailable->render();

            foreach ($this->strayHosts($rendered) as $host) {
                $offences[] = "{$label} sends members to {$host}";
            }
        }

        $this->assertSame([], $offences, $this->explain($offences));
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5), BOTH DIRECTIONS.
     *
     * The planted string is the exact body that was live for weeks. The clean
     * sample is its replacement. Without the second half this passes against a
     * reader that flags nothing.
     */
    public function test_the_reader_sees_the_staging_host_and_leaves_production_alone(): void
    {
        $planted = "Hello Aisha, someone new is waiting.\n\nOpen it here: realvows.skillleo.com/dashboard";
        $clean = "Hello Aisha, someone new is waiting.\n\nOpen it here: realvows.com/dashboard";

        $this->assertSame(
            ['realvows.skillleo.com'],
            $this->strayHosts($planted),
            'The reader cannot see the host that was actually in six approved templates.'
        );

        $this->assertSame(
            [],
            $this->strayHosts($clean),
            'The reader flags the production host, which would make it noise.'
        );

        // And it catches the VPS hostname, which is the other way a member
        // could be sent somewhere that is ours and still wrong.
        $this->assertNotSame([], $this->strayHosts('Open it at srv1896577.hstgr.cloud/dashboard'));
    }

    /**
     * ⚠️ THE LIST OF FORBIDDEN HOSTS MUST NOT ROT.
     *
     * NEVER_TO_A_MEMBER exists to make the failure message specific. The reader
     * itself works by allow-list, so it catches hosts nobody has thought of.
     * This proves that, because a deny-list that has quietly become the only
     * mechanism would pass a host simply because nobody added it.
     */
    public function test_an_unknown_host_is_caught_without_being_on_any_list(): void
    {
        $this->assertSame(
            ['some-random-host.example.net'],
            $this->strayHosts('Open it at https://some-random-host.example.net/dashboard'),
            'The reader works by deny-list rather than allow-list, so it only catches hosts '
                .'somebody remembered to forbid.'
        );
    }

    /** @return array<string, \Illuminate\Mail\Mailable> */
    private function mailables(User $recipient): array
    {
        $other = Profile::factory()->man()->create(['first_name' => 'Idris']);

        return [
            'someone-interested' => new SomeoneInterested($recipient),
            'introduction-delivered' => new IntroductionDelivered($recipient, 1),
            'mutual-interest' => new MutualInterest($recipient, $other),
        ];
    }

    /**
     * ⚠️ TWO READERS, BECAUSE EACH HAS A HOLE THE OTHER COVERS.
     *
     * The allow-list reader catches hosts nobody has thought of, which is the
     * property a deny-list can never have. But it finds hosts by matching a
     * TLD from a list, so a host on a TLD nobody added is invisible to it —
     * and that is not hypothetical: it could not see `srv1896577.hstgr.cloud`,
     * the client's own VPS hostname, because `.cloud` was not on the list.
     *
     * The exact reader catches the specific hosts we know are wrong,
     * regardless of TLD.
     *
     * Neither alone is sufficient. Together, an unknown host on a known TLD
     * and a known host on an unknown TLD are both caught, and the failure
     * message names which reader fired.
     *
     * @return list<string>
     */
    private function strayHosts(string $text): array
    {
        $found = array_values(array_filter(
            Scanners::hosts($text),
            fn (string $host) => ! in_array($host, [...self::PRODUCTION_HOSTS, 'www.w3.org', 'example.com'], true),
        ));

        foreach (self::NEVER_TO_A_MEMBER as $host) {
            if (stripos($text, $host) !== false && ! in_array($host, $found, true)) {
                $found[] = $host;
            }
        }

        return $found;
    }

    private function explain(array $offences): string
    {
        return "A message sent to a member names a host that is not production. When delivery "
            ."switches on, real members follow that link to the wrong server, and the message "
            ."cannot be recalled. DO NOT fix this by adding the host to PRODUCTION_HOSTS:\n  "
            .implode("\n  ", $offences);
    }
}
