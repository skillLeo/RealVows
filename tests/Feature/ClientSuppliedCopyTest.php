<?php

namespace Tests\Feature;

use App\Support\FoundingOffer;
use App\Support\PublicState;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE LINES THE CLIENT WROTE HIMSELF, PINNED SO NOBODY HAS TO REMEMBER.
 *
 * R9 recomposed every public page against a visual reference. A recompose is
 * precisely when a sentence gets reworded to fit a new column width, and the
 * sentences most likely to be reworded are the short declarative ones — which
 * is exactly what the client's own lines are.
 *
 * ⚠️ THIS EXISTS BECAUSE THE COUNT WAS WRONG. The number of protected lines was
 * carried as "four" in a brief, and going to find them turned up three on the
 * public pages and one on the legal pages, which are out of scope. A number in
 * somebody's memory is not a record. This file is the record.
 *
 * ── ⚠️ VERBATIM MEANS AS RENDERED, NOT AS HE TYPED IT ─────────────────────
 *
 * Two of these were adapted deliberately and the adaptations are documented
 * beside them in the templates:
 *
 *  · He sent "The person to build a life with." It was corrected for person so
 *    that it addresses the reader, and nothing else.
 *  · He sent "be one of the first 100 people to create your account and get
 *    MATCHED for 100% FREE". The capitals are his emphasis and shouting is the
 *    register of a discount site, so the weight lands through the brass accent
 *    instead. The hundred is counted rather than typed, because written into a
 *    template it becomes a lie the moment the hundred and first person joins.
 *
 * So this pins what a visitor actually reads. Pinning his raw text would fail
 * on the day it was written and would be arguing with a decision he approved.
 */
class ClientSuppliedCopyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ HIS, NOT OURS. Every line here was supplied or directed by the client.
     * Do not add ours to this list: a protected list that grows to include
     * everything protects nothing, and the next person cannot tell which
     * sentences carry a promise to somebody.
     *
     * @var array<string, string>
     */
    /*
     * ⚠️ ONE LINE WAS RETIRED IN R10-B, BY HIM, AND IT IS RECORDED RATHER THAN
     * DELETED.
     *
     *   "The person you build a life with."
     *   Provenance: his line, corrected only for person. Protected since R7.
     *
     * It was the hero paragraph. R10-B §2 replaces that paragraph with the
     * wording from the reference he supplied — "A premium matchmaking platform
     * for people who have decided they want marriage — built on rigorous
     * verification, values-based alignment, and profile compatibility." — which
     * is also his, and later.
     *
     * This guard exists to stop US quietly dropping his words in a redesign,
     * and it did its job: it failed the build the moment the line went. But it
     * cannot arbitrate between two things the client said, and when it does,
     * the more recent instruction is the one that stands. His own words this
     * round were "I do not want our words. I want the same words that they
     * have. Every single thing."
     *
     * Left here as a comment so the line is recoverable and the supersession is
     * legible, rather than vanishing from the file with the guard.
     */
    private const PROTECTED = [

        'RealVows is for people looking to find their true soul mate.' =>
            'His line. It replaced "RealVows is for people ready to marry" at his direct '
            .'instruction, so it is not decorative and does not get reworded to fit a column.',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);
    }

    private function home(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_every_protected_line_survives_on_the_home_page(): void
    {
        $home = $this->home();

        foreach (self::PROTECTED as $line => $provenance) {
            $this->assertStringContainsString(
                $line,
                $home,
                "A line the client wrote himself is no longer on the home page:\n\n  \"{$line}\"\n\n"
                    ."Provenance: {$provenance}\n\n"
                    .'Losing one of his lines in a redesign is worse than shipping late. If it '
                    .'genuinely has to move, ask him first.',
            );
        }
    }

    /**
     * ⚠️ THE FOUNDING LINE IS CONDITIONAL, SO IT IS PINNED SEPARATELY.
     *
     * The band only renders while places remain, and it reads a projection
     * rather than the database. A test that skipped the projection would assert
     * against a path no visitor ever takes.
     */
    public function test_the_founding_line_survives_while_places_remain(): void
    {
        PublicState::write(true, 42);

        $home = $this->home();

        $this->assertStringContainsString('Founding members', $home);
        $this->assertStringContainsString(
            'Be one of the first '.FoundingOffer::PLACES.' people to join',
            $home,
            'The founding line is gone. It is his, and it is the reason the count exists at all.',
        );
        /*
         * ⚠️ HIS CAPITALS, PINNED. The client asked personally that free signup
         * be unmissable and this is how he wrote it. A later round smoothing
         * "100% FREE" into sentence case is exactly the kind of tidy-up that
         * feels like an improvement and removes his emphasis.
         */
        $this->assertStringContainsString(
            '100% FREE',
            $home,
            'His capitals are gone from the founding band.',
        );

        /*
         * ⚠️ AND THE PROMISE MATCHES WHAT THE CODE ENFORCES. Permanence is per
         * introduction, stamped by free_period_granted_at, not a standing
         * benefit of being in the hundred. Nothing marks a founding account.
         */
        $this->assertStringContainsString('stays open permanently', $home);
        $this->assertStringNotContainsString(
            'every introduction you receive is',
            $home,
            'The band is back to promising the hundred free introductions forever, which no '
                .'flag on any account enforces.',
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND WITHOUT IT THIS FILE IS DECORATION.
     *
     * Every assertion above is "this string is present", and every one of them
     * would also pass against a page that happened to contain the words for an
     * unrelated reason, or against a checker looking at the wrong document.
     * This proves the same reader reports a missing line as missing.
     */
    public function test_the_reader_notices_when_a_line_is_gone(): void
    {
        $home = $this->home();

        $this->assertStringNotContainsString(
            'The person to build a life with.',
            $home,
            'The uncorrected version of his line is on the page, which means the correction '
                .'for person was undone.',
        );

        // A sentence that is deliberately NOT on this page, proving the check
        // distinguishes present from absent rather than answering yes to all.
        $this->assertStringNotContainsString('RealVows is for people ready to marry', $home);
    }

    /**
     * ⚠️ THE LEGAL PAGES ARE OUT OF SCOPE AND CARRY ONE OF HIS SENTENCES.
     *
     * Listed here so a future round does not disturb it while looking only at
     * this file for what is protected.
     */
    public function test_his_line_on_the_terms_is_still_there(): void
    {
        $terms = $this->get('/terms')->assertOk()->getContent();

        $this->assertStringContainsString('aged 18 and over', $terms);
        $this->assertStringContainsString('leading to marriage', $terms);
    }
}
