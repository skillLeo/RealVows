<?php

namespace Tests\Feature;

use App\Http\Controllers\LegalController;
use App\Services\Settings\SettingsService;
use App\Support\PhotoVisibilityPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Scanners;
use Tests\TestCase;

/**
 * ⚠️ THE PRIVACY POLICY MUST DESCRIBE THIS PRODUCT, NOT THE ONE IT USED TO BE.
 *
 * ── What went wrong ───────────────────────────────────────────────────────
 *
 * The page said "members you are introduced to see your profile and your
 * photographs". Photographs stopped going to members. The sentence stayed.
 *
 * That is not a stale line in a footer. It is a false statement about the most
 * sensitive material the platform holds, printed on the one page a person reads
 * in order to decide whether to hand it over. The Terms carried the same claim
 * as a granted permission, which is worse: a licence wider than the behaviour,
 * on a document the member agrees to.
 *
 * Nothing caught it because nothing asked. Every test about photographs asked
 * the presenter and the route, correctly and thoroughly, and no test ever asked
 * the page that describes them.
 *
 * ── ⚠️ WHY THE PHOTOGRAPH SENTENCE IS GENERATED, NOT WRITTEN ──────────────
 *
 * Who may see a photograph is a setting, because it is a religious question the
 * client's scholar answers. Any sentence typed into the policy is true until he
 * answers and false immediately afterwards, which is precisely how the first
 * one went wrong. So the policy reads the live level, and this file asserts
 * that all three levels produce different text and that none of them reproduces
 * the old false claim.
 */
class LegalPagesTellTheTruthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function text(string $path): string
    {
        return Scanners::visibleText($this->get($path)->assertOk()->getContent());
    }

    private function level(string $level): void
    {
        app(SettingsService::class)->set(
            PhotoVisibilityPolicy::GROUP,
            PhotoVisibilityPolicy::KEY,
            $level,
        );
        app(SettingsService::class)->flush();
    }

    // ── The four things that were missing entirely ───────────────────────

    public function test_the_privacy_policy_covers_what_it_has_to_cover(): void
    {
        $text = strtolower($this->text('/privacy'));

        $required = [
            'photographs are the most sensitive' => 'how photographs are treated',
            'same gender as you' => 'that the reviewer is of the same gender',
            'never collect a passport' => 'that no government identity document is collected',
            'if you name a trusted contact' => 'the trusted contact as a data subject',
            'without going through you' => "the trusted contact's independent rights",
            'artificial intelligence' => 'the AI disclosure',
            'never sent to any such provider' => 'what is never sent to a provider',
            'device and connection' => 'device and technical data',
        ];

        $missing = [];

        foreach ($required as $needle => $what) {
            if (! str_contains($text, $needle)) {
                $missing[] = $what;
            }
        }

        $this->assertSame([], $missing, implode("\n  ", array_merge([
            'The privacy policy no longer covers these, and each one is something the platform',
            'actually does to real people:',
        ], $missing)));
    }

    /**
     * ⚠️ THE EXACT SENTENCE THAT WAS FALSE, BANNED BY NAME ON BOTH PAGES.
     *
     * A test asserting the policy "mentions photographs" would have passed the
     * whole time the wrong sentence was on the page.
     */
    public function test_no_legal_page_claims_members_see_photographs(): void
    {
        foreach (['/privacy', '/terms'] as $path) {
            $text = strtolower($this->text($path));

            $this->assertStringNotContainsString(
                'introduced to see your profile and your photographs',
                $text,
                "{$path} has gone back to claiming members see photographs.",
            );

            $this->assertStringNotContainsString(
                'show them to members you are introduced to',
                $text,
                "{$path} grants a permission to show photographs to members that the product "
                    .'does not use and does not want.',
            );
        }
    }

    public function test_the_photograph_sentence_follows_the_live_setting(): void
    {
        $sentences = [];

        foreach (PhotoVisibilityPolicy::levels() as $level) {
            $this->level($level);
            $sentences[$level] = $this->text('/privacy');
        }

        /*
         * ⚠️ ALL THREE MUST DIFFER. A policy that printed the same paragraph at
         * every level would pass any single-level assertion while telling two
         * thirds of its readers something untrue.
         */
        $this->assertCount(
            3,
            array_unique($sentences),
            'Two photo-visibility levels produce the same privacy policy, so the page is not '
                .'actually reading the setting.'
        );

        // And each says the thing that level actually does.
        $this->assertStringContainsString(
            'No other member ever sees them',
            $sentences[PhotoVisibilityPolicy::NEVER],
        );
        $this->assertStringContainsString(
            'once you have both said yes',
            $sentences[PhotoVisibilityPolicy::AT_MUTUAL_INTEREST],
        );
    }

    // ── The Terms ────────────────────────────────────────────────────────

    public function test_the_terms_treat_the_trusted_contact_as_a_party(): void
    {
        $text = strtolower($this->text('/terms'));

        foreach ([
            'your trusted contact',
            'they agree to these terms',
            'standing in your way',
        ] as $needle) {
            $this->assertStringContainsString(
                $needle,
                $text,
                'The Terms no longer set out the trusted contact as a participant with '
                    .'obligations. They hold a password, a photograph and a decision record, '
                    .'and they never signed up on their own initiative.',
            );
        }
    }

    public function test_the_terms_say_where_our_responsibility_ends(): void
    {
        $text = strtolower($this->text('/terms'));

        $this->assertStringContainsString('we introduce, and we do not guarantee', $text);
        $this->assertStringContainsString(
            'happens between the two of you',
            $text,
            'The Terms do not say that what follows a contact exchange is outside the platform. '
                .'A member who assumed otherwise would be assuming a protection that does not '
                .'exist.',
        );
    }

    public function test_the_terms_still_print_pending_until_the_entity_is_confirmed(): void
    {
        /*
         * ⚠️ THIS MUST NOT BE "FIXED". Only the client's bank can confirm the
         * contracting company, and naming an unconfirmed one in an agreement is
         * worse than naming none.
         */
        $this->assertStringContainsString(
            'contracting entity is not yet confirmed',
            strtolower($this->text('/terms')),
        );
    }

    // ── Versioning ───────────────────────────────────────────────────────

    public function test_every_legal_page_is_versioned_and_says_how_to_get_an_older_one(): void
    {
        $seen = [];

        foreach (['/terms', '/privacy', '/refunds', '/community-guidelines'] as $path) {
            $text = $this->text($path);

            $this->assertStringContainsString(
                'Version '.LegalController::VERSION,
                $text,
                "{$path} carries no version. The Terms promise to tell a member before a change "
                    .'takes effect, which means nothing if the document has no name.',
            );

            $this->assertStringContainsString('wording that was in force', $text);

            $seen[] = $path;
        }

        $this->assertCount(4, $seen);
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above is "this page contains this
     * string", and a helper that returned the whole rendered document for every
     * path — or the same document four times — would satisfy a great many of
     * them for the wrong reason.
     */
    public function test_the_reader_distinguishes_the_pages(): void
    {
        $privacy = $this->text('/privacy');
        $terms = $this->text('/terms');

        $this->assertNotEmpty($privacy);
        $this->assertNotSame($privacy, $terms, 'Two different legal pages rendered identically.');

        // A phrase only one of them carries, in each direction.
        $this->assertStringContainsString('never collect a passport', $privacy);
        $this->assertStringNotContainsString('never collect a passport', $terms);

        $this->assertStringContainsString('You may hold one account', $terms);
        $this->assertStringNotContainsString('You may hold one account', $privacy);
    }
}
