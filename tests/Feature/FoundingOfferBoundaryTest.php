<?php

namespace Tests\Feature;

use App\Models\Profile;
use App\Models\User;
use App\Models\Verification;
use App\Support\FoundingOffer;
use App\Support\PublicState;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ "BE ONE OF THE FIRST 100" MUST NEVER BE A SENTENCE THE DATABASE
 * CONTRADICTS.
 *
 * The offer already counts verified members rather than being typed into the
 * template, and PublicState already reads as CLOSED when the projection cannot
 * be read. Both are right. Neither was ever exercised AT THE EDGE, and the edge
 * is the only place this claim can go wrong.
 *
 * The failure is not theoretical and it is not recoverable. On the day the
 * hundredth person verifies, a wrong comparison leaves a public page promising
 * something the client has already given away — to the hundred and first
 * person, who then paid, having read otherwise. There is no fix after the fact
 * that is not a refund and an apology.
 *
 * ── ⚠️ WHY THIS GOES ALL THE WAY TO THE RENDERED HTML ─────────────────────
 *
 * Asserting that remaining() returns 1 at ninety-nine proves the arithmetic and
 * nothing about the promise. Three things sit between that number and the
 * member's eye: the scheduler's projection, PublicState's reading of it, and a
 * Blade conditional. The claim is only true if the last of those is right, so
 * the band is looked for in the actual response body.
 *
 * ⚠️ AND BOTH DIRECTIONS AT EVERY BOUNDARY. A page that never showed the band
 * would satisfy every "it is gone at 100" assertion in this file while quietly
 * withholding the client's own offer from everybody.
 */
class FoundingOfferBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ⚠️ THE PROJECTION IS A REAL FILE IN storage/app AND THIS TEST WRITES IT.
     *
     * There is no faking it: the public pages read the file, so a test that
     * mocked the read would be exercising a path no visitor takes. But that
     * means the suite mutates the developer's own environment — and the last
     * case here DELETES the projection deliberately, which would leave the
     * local site with the founding offer closed and nothing to explain why.
     *
     * That is exactly the fault found in Storage::fake('private'): a test
     * reaching into real storage and leaving something behind that the next run
     * silently depends on. Backed up and put back.
     */
    private ?string $projectionBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(SettingsSeeder::class);

        $this->projectionBackup = is_file(PublicState::path())
            ? file_get_contents(PublicState::path())
            : null;
    }

    protected function tearDown(): void
    {
        if ($this->projectionBackup !== null) {
            file_put_contents(PublicState::path(), $this->projectionBackup);
        } elseif (is_file(PublicState::path())) {
            @unlink(PublicState::path());
        }

        parent::tearDown();
    }

    /**
     * ⚠️ REAL ROWS, NOT A MOCKED COUNT. The question is whether the offer
     * closes when a hundred people have verified, and a stubbed counter answers
     * a different question that happens to look the same.
     */
    private function verifiedMembers(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            $profile = Profile::factory()->create(['user_id' => $user->id]);

            Verification::create([
                'profile_id' => $profile->id,
                'status' => Verification::STATUS_VERIFIED,
                'code' => 'AB12CD',
                'verified_at' => now(),
            ]);
        }
    }

    private function project(): void
    {
        // What the scheduler does. The public pages read the file, never the
        // database, so a test that skipped this would be testing a path no
        // visitor ever takes.
        PublicState::write(true, app(FoundingOffer::class)->remaining());
    }

    private function homeMentionsTheOffer(): bool
    {
        $html = $this->get('/')->getContent();

        return str_contains($html, 'Founding members');
    }

    public function test_at_ninety_nine_one_place_is_left_and_the_page_says_so(): void
    {
        $this->verifiedMembers(99);
        $this->project();

        $this->assertSame(1, app(FoundingOffer::class)->remaining());
        $this->assertSame(1, PublicState::foundingPlacesLeft());
        $this->assertTrue(PublicState::foundingOfferOpen());

        $this->assertTrue(
            $this->homeMentionsTheOffer(),
            'One place was left and the home page did not offer it. This is the direction '
                .'nobody notices, because a missing band looks like a design choice.'
        );
    }

    public function test_at_one_hundred_the_offer_is_closed_and_the_band_is_gone(): void
    {
        /*
         * ⚠️ THE EXACT BOUNDARY, AND THE ONE AN OFF-BY-ONE GETS WRONG. The
         * hundredth person has taken the hundredth place. Nothing is left.
         */
        $this->verifiedMembers(100);
        $this->project();

        $this->assertSame(0, app(FoundingOffer::class)->remaining());
        $this->assertFalse(PublicState::foundingOfferOpen());

        $this->assertFalse(
            $this->homeMentionsTheOffer(),
            'The hundredth member has verified and the home page still promises a place '
                .'among the first hundred.'
        );
    }

    public function test_at_one_hundred_and_one_it_stays_closed_and_never_goes_negative(): void
    {
        $this->verifiedMembers(101);
        $this->project();

        // Not -1. A template must never be able to render "minus one place left".
        $this->assertSame(0, app(FoundingOffer::class)->remaining());
        $this->assertSame(0, PublicState::foundingPlacesLeft());
        $this->assertFalse($this->homeMentionsTheOffer());
    }

    public function test_an_unverified_crowd_does_not_close_the_offer(): void
    {
        /*
         * ⚠️ WHAT IS COUNTED IS THE POINT OF THE WHOLE FEATURE. The offer is
         * about being INTRODUCED free, and nobody is introduced before a person
         * has checked them. Counting registrations instead would let a hundred
         * abandoned sign-ups close an offer nobody took — the client would have
         * given away nothing and the page would have stopped saying so.
         */
        $this->verifiedMembers(40);

        for ($i = 0; $i < 200; $i++) {
            $user = User::factory()->create();
            $profile = Profile::factory()->create(['user_id' => $user->id]);

            Verification::create([
                'profile_id' => $profile->id,
                'status' => Verification::STATUS_PENDING,
                'code' => 'AB12CD',
            ]);
        }

        $this->project();

        $this->assertSame(60, app(FoundingOffer::class)->remaining());
        $this->assertTrue($this->homeMentionsTheOffer());
    }

    public function test_an_unreadable_projection_reads_as_closed(): void
    {
        /*
         * The opposite direction from the free period, deliberately. "Joining
         * is free" is a standing statement about how the product runs. "You can
         * be one of the first hundred" is a specific promise about a specific
         * number, and a promise must never be made on a guess.
         */
        $this->verifiedMembers(1);

        // The projection is a file on disk. Deleting it IS the cold-start
        // condition, and a mocked reader would test a different thing.
        @unlink(PublicState::path());

        $this->assertFalse(PublicState::foundingOfferOpen());
        $this->assertFalse(
            $this->homeMentionsTheOffer(),
            'With no projection to read, the page promised a place among the first hundred '
                .'without knowing how many were left.'
        );
    }

    public function test_the_page_can_still_show_the_band_at_all(): void
    {
        /*
         * ⚠️ THE CONTROL THE WHOLE FILE RESTS ON. Four assertions above are
         * "the band is absent", and a template that had lost the band entirely
         * — a renamed class, a broken conditional, a deleted paragraph — would
         * satisfy every one of them while the client's offer never appeared to
         * anybody.
         */
        $this->verifiedMembers(0);
        $this->project();

        $this->assertSame(FoundingOffer::PLACES, app(FoundingOffer::class)->remaining());
        $this->assertTrue(
            $this->homeMentionsTheOffer(),
            'With a hundred places open the home page still did not mention the offer, so '
                .'every absence asserted in this file means "the band is broken" rather than '
                .'"the offer has closed".'
        );
    }
}
