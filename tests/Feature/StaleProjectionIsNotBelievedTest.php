<?php

namespace Tests\Feature;

use App\Support\PublicState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * ⚠️ FAIL-CLOSED ANSWERED "UNREADABLE" AND SAID NOTHING ABOUT "OLD".
 *
 * The public pages read a projection rather than the database, and an
 * unreadable projection has always read as closed. A projection that parses
 * perfectly and was written three days ago read as current, which is the same
 * false statement arriving through a different door: the scheduler dies, the
 * file sits there, and the home page keeps offering places that ran out on
 * Tuesday.
 *
 * ⚠️ THE TIMESTAMP WAS ALREADY BEING RECORDED AND ALREADY BEING READ. `write()`
 * stamped `written_at`, `read()` returned it, and not one caller ever looked at
 * it. The fact needed was already in the file.
 */
class StaleProjectionIsNotBelievedTest extends TestCase
{
    use RefreshDatabase;

    /** Write a projection as though the scheduler ran $minutes ago. */
    private function projectionAgedBy(int $minutes): void
    {
        $this->travel(-$minutes)->minutes();
        PublicState::write(true, 42);
        $this->travelBack();
    }

    // ── The boundary ─────────────────────────────────────────────────────

    public function test_at_fourteen_minutes_the_projection_is_still_believed(): void
    {
        $this->projectionAgedBy(14);

        $this->assertTrue(
            PublicState::foundingOfferOpen(),
            'A fourteen minute old projection was rejected. The writer runs every minute, so a '
                .'threshold this tight flickers the band off for real visitors on one slow host.',
        );
        $this->assertSame(42, PublicState::foundingPlacesLeft());
    }

    public function test_at_sixteen_minutes_it_is_not(): void
    {
        $this->projectionAgedBy(16);

        $this->assertFalse(
            PublicState::foundingOfferOpen(),
            'A sixteen minute old projection was still being believed. The scheduler can be dead '
                .'and the home page would go on promising places that ran out.',
        );
        $this->assertSame(0, PublicState::foundingPlacesLeft());
    }

    // ── ⚠️ Each getter keeps its own fail direction ──────────────────────

    /**
     * ⚠️ THE DIRECTIONS ARE OPPOSITE ON PURPOSE AND STALENESS MUST NOT FLATTEN
     * THEM.
     *
     * "Joining is free" is a standing statement about how the product runs and
     * is safe to assume. "You can be one of the first hundred" is a promise
     * about a specific number and must never be a guess. Rejecting on age
     * returns null, which hands each getter the direction it already chose,
     * rather than inventing a new one.
     */
    public function test_a_stale_projection_still_reads_as_free_rather_than_paid(): void
    {
        $this->projectionAgedBy(60);

        $this->assertTrue(
            PublicState::freePeriodActive(),
            'A stale projection made the pages say joining costs money. Over-promising free is '
                .'a mistake we would honour. Under-promising loses the member silently.',
        );

        $this->assertFalse(
            PublicState::pricingLinkVisible(),
            'A stale projection revealed the pricing link.',
        );
    }

    // ── ⚠️ The silent failure is made loud ───────────────────────────────

    /**
     * ⚠️ CLOSING THE BAND IS CORRECT AND LOOKS EXACTLY LIKE AN OFFER THAT RAN
     * OUT.
     *
     * Without this line the scheduler can be dead for two days while real
     * visitors are refused an offer that is genuinely open, and the first
     * symptom is signups that did not happen.
     */
    public function test_ignoring_a_stale_projection_is_written_down(): void
    {
        Log::spy();

        $this->projectionAgedBy(45);
        PublicState::foundingOfferOpen();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []) => str_contains($message, 'stale')
                && ($context['age_seconds'] ?? 0) > PublicState::STALE_AFTER_SECONDS)
            ->atLeast()->once();
    }

    /**
     * ⚠️ POSITIVE CONTROL, AND WITHOUT IT EVERY ASSERTION ABOVE PASSES AGAINST
     * A PROJECTION SYSTEM THAT HAS SIMPLY STOPPED WORKING.
     *
     * "The offer is closed" is what a stale projection produces AND what a
     * broken writer, a missing file and a wrong path produce. This proves a
     * fresh projection is believed, so the refusals above are the threshold
     * doing its job rather than the mechanism being dead.
     */
    public function test_a_fresh_projection_is_believed_and_says_nothing(): void
    {
        Log::spy();

        PublicState::write(true, 7);

        $this->assertTrue(PublicState::foundingOfferOpen());
        $this->assertSame(7, PublicState::foundingPlacesLeft());
        $this->assertFalse(PublicState::isStaleNow());

        Log::shouldNotHaveReceived('warning');
    }

    /** ⚠️ A projection with no timestamp cannot prove it is current. */
    public function test_a_projection_with_no_timestamp_is_treated_as_stale(): void
    {
        file_put_contents(
            PublicState::path(),
            "<?php\n\nreturn ".var_export(['free_period' => true, 'founding_places_left' => 50], true).";\n",
        );

        $this->assertFalse(PublicState::foundingOfferOpen());
        $this->assertTrue(PublicState::isStaleNow());
    }

    public function test_the_age_reported_to_the_admin_panel_is_real(): void
    {
        $this->projectionAgedBy(3);

        $age = PublicState::ageInSeconds();

        $this->assertNotNull($age);
        $this->assertGreaterThanOrEqual(150, $age, 'The reported age is far too small.');
        $this->assertLessThanOrEqual(300, $age, 'The reported age is far too large.');
    }
}
