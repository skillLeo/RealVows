<?php

namespace Tests\Feature;

use App\Support\PublicState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\PublicSurfaces;
use Tests\TestCase;

/**
 * ⚠️ A DATABASE FAULT MUST NOT BE ABLE TO TAKE DOWN THE MARKETING PAGES.
 *
 * It already did once. A free-period read was added to the Inertia middleware,
 * which is appended to the `web` group and therefore runs on every web request
 * — including the Blade home page, which is not Inertia and never reads the
 * prop. An unmigrated settings table turned the front page of the product into
 * a 500.
 *
 * The nav made that risk worse rather than better: a Pricing link that has to
 * know whether the free period is on would have put the same query back on the
 * same page, deliberately this time.
 *
 * ⚠️ SO THE FIX IS NOT "MAKE THE READ FAIL GRACEFULLY". It is that these pages
 * do not read the database at all. The one fact they need is projected into a
 * file. This proves it, by taking the database away entirely and asking for
 * every public page.
 *
 * ⚠️ NOTE THIS DOES NOT USE RefreshDatabase. The whole point is to run with no
 * usable connection, and a trait that migrates in setUp would defeat it.
 */
class PublicPagesSurviveTheDatabaseTest extends TestCase
{
    protected function tearDown(): void
    {
        File::delete(PublicState::path());

        parent::tearDown();
    }

    /**
     * ⚠️ THE COLD PATH: no projection AND no database. Both gone at once,
     * which is the state a fresh deploy is in for the seconds before the
     * scheduler first runs.
     */
    public function test_every_public_page_renders_with_no_database_and_no_projection(): void
    {
        File::delete(PublicState::path());
        $this->breakTheDatabase();

        foreach (PublicSurfaces::paths() as $path) {
            $response = $this->get($path);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$path} did not render without a database. A marketing page must not depend "
                    ."on one."
            );

            /*
             * ⚠️ AND THE PRICING LINK IS ABSENT, not merely the page alive.
             * Rendering a 200 that links to /packages during the free period
             * would be the failure this whole design exists to prevent — a
             * navigation entry pointing at a deliberate 404.
             */
            $this->assertStringNotContainsString(
                '/packages',
                $response->getContent(),
                "{$path} offered a Pricing link with no way to know whether payments are on. "
                    ."The cold path must hide it, not guess."
            );
        }
    }

    /**
     * The projection says payments are live, so the link appears — proving the
     * assertion above is about state rather than about the string never being
     * rendered at all.
     */
    public function test_the_pricing_link_appears_when_the_projection_says_payments_are_live(): void
    {
        PublicState::write(false);
        $this->breakTheDatabase();

        $content = $this->get('/')->getContent();

        $this->assertStringContainsString(
            '/packages',
            $content,
            'With the projection saying payments are live, the Pricing link must be offered — '
                .'otherwise the previous test passes because the link is never rendered at all.'
        );
    }

    public function test_the_projection_hides_pricing_during_the_free_period(): void
    {
        PublicState::write(true);

        $this->assertFalse(PublicState::pricingLinkVisible());
        $this->assertTrue(PublicState::freePeriodActive());

        PublicState::write(false);

        $this->assertTrue(PublicState::pricingLinkVisible());
        $this->assertFalse(PublicState::freePeriodActive());
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). `breakTheDatabase()` is the entire
     * mechanism of the first test, and if it quietly stopped working — a
     * driver that reconnects, a config key that moves — the test would keep
     * passing against a perfectly healthy database and prove nothing at all.
     */
    public function test_breaking_the_database_actually_breaks_it(): void
    {
        $this->breakTheDatabase();

        $this->expectException(\Throwable::class);

        DB::table('settings')->count();
    }

    /**
     * ⚠️ A CORRUPT PROJECTION IS TREATED AS A MISSING ONE. A half-written file
     * from a disk that filled up must not fatal a page.
     */
    public function test_a_corrupt_projection_is_ignored_rather_than_fatal(): void
    {
        File::put(PublicState::path(), '<?php return "not an array";');

        $this->assertFalse(PublicState::pricingLinkVisible());
        $this->assertTrue(PublicState::freePeriodActive());

        $this->breakTheDatabase();
        $this->assertSame(200, $this->get('/')->getStatusCode());
    }

    /**
     * Point the connection at something that cannot answer, and drop the
     * existing one so nothing is served from a live handle.
     */
    private function breakTheDatabase(): void
    {
        config([
            'database.connections.unreachable' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => '1',
                'database' => 'nothing_here',
                'username' => 'nobody',
                'password' => '',
            ],
            'database.default' => 'unreachable',
        ]);

        DB::purge();
    }
}
