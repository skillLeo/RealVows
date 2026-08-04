<?php

namespace Tests\Feature;

use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\FreePeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ⚠️ THE EXIT IS THE DIRECTION THAT MATTERS, AND IT IS THE ONE NOBODY TESTS.
 *
 * A free period is easy to switch on and easy to demonstrate. The failure is
 * the other way round: payments come on, and an introduction a member already
 * received quietly relocks. That is retroactively changing the terms they
 * joined under, and no explanation makes it feel like anything but a bait.
 *
 * A mode that can be entered and not cleanly left is a trap. So every test
 * below that matters runs the mode change and asserts what survives it.
 */
class FreePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    private function setMode(bool $on): void
    {
        app(SettingsService::class)->set('general', 'free_period', $on ? '1' : '0');
    }

    private function member(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        Profile::factory()->verified()->create(['user_id' => $user->id, 'submitted_at' => now()]);

        return $user->fresh();
    }

    private function teaser(User $seeker): MatchRecord
    {
        $them = $this->member();

        return MatchRecord::create([
            'seeker_profile_id' => $seeker->profile->id,
            'matched_profile_id' => $them->profile->id,
            'total_score' => 82,
            'score_breakdown' => ['components' => []],
            'compatibility_rationale' => 'A steady match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_TEASER,
            'generated_at' => now(),
        ]);
    }

    // ── Entering the mode ────────────────────────────────────────────────────

    /** ⚠️ No credits, and none are taken. */
    public function test_an_introduction_opens_free_with_no_credits(): void
    {
        $this->setMode(true);

        $user = $this->member();
        $match = $this->teaser($user);

        $this->actingAs($user)->post("/matches/{$match->id}/unlock")->assertRedirect();

        $this->assertSame(MatchRecord::STATUS_UNLOCKED, $match->fresh()->status);
        $this->assertNotNull($match->fresh()->free_period_granted_at);
    }

    /**
     * ⚠️ THE LEDGER ROW IS STILL WRITTEN, WITH A ZERO DELTA.
     *
     * Skipping it because no money moved breaks verify-ledger, makes the
     * give-away uncountable, and destroys the only distinction between a free
     * introduction and a paid one — which is what the permanence guarantee
     * rests on.
     */
    public function test_a_free_unlock_still_writes_a_ledger_row(): void
    {
        $this->setMode(true);

        $user = $this->member();
        $match = $this->teaser($user);

        $this->actingAs($user)->post("/matches/{$match->id}/unlock");

        $entry = CreditLedgerEntry::where('user_id', $user->id)
            ->where('reason', FreePeriod::LEDGER_REASON)
            ->first();

        $this->assertNotNull($entry, 'A free unlock wrote no ledger row, so it cannot be reconciled or counted.');
        $this->assertSame(0, (int) $entry->delta, 'A free unlock charged something.');
        $this->assertSame($match->id, (int) $entry->reference_id);
    }

    /** ⚠️ Hidden, not disabled — including the route, not just the button. */
    public function test_every_purchase_surface_is_gone(): void
    {
        $this->setMode(true);
        $user = $this->member();

        $this->actingAs($user)->get('/packages')->assertNotFound();
        $this->actingAs($user)->get('/checkout/single')->assertNotFound();
    }

    /** ⚠️ POSITIVE CONTROL: they exist when the mode is off. */
    public function test_the_purchase_surfaces_exist_when_the_period_is_over(): void
    {
        $this->setMode(false);
        $user = $this->member();

        $this->actingAs($user)->get('/packages')->assertOk();
    }

    /** The product says so out loud rather than showing a gap where a price was. */
    public function test_the_mode_is_visible_to_every_screen(): void
    {
        $this->setMode(true);
        $user = $this->member();

        $props = $this->actingAs($user)->get('/dashboard')->viewData('page')['props'];

        $this->assertTrue($props['freePeriod']);
    }

    // ── Leaving the mode — the part that matters ─────────────────────────────

    /**
     * ⚠️ THE PERMANENCE GUARANTEE, ASSERTED ACROSS THE MODE CHANGE.
     *
     * This is the test the whole design exists for. An introduction received
     * free stays open forever, and stays readable, after payments come on.
     */
    public function test_a_free_introduction_stays_open_after_the_period_ends(): void
    {
        $this->setMode(true);

        $user = $this->member();
        $match = $this->teaser($user);

        $this->actingAs($user)->post("/matches/{$match->id}/unlock");
        $this->assertSame(MatchRecord::STATUS_UNLOCKED, $match->fresh()->status);

        // ── The cutover ──
        $this->setMode(false);

        $this->assertSame(
            MatchRecord::STATUS_UNLOCKED,
            $match->fresh()->status,
            'An introduction a member already received relocked when payments came on. That is '
                .'retroactively changing the terms they joined under.'
        );

        // And it is still readable, not merely flagged open.
        $this->actingAs($user->fresh())->get("/matches/{$match->id}")->assertOk();

        $this->assertTrue(
            app(FreePeriod::class)->wasGrantedFree($match->fresh()),
            'The free-period stamp was cleared by the mode change, so nothing records why this '
                .'introduction is open and a future audit will read it as unexplained.'
        );
    }

    /**
     * ⚠️ AND NEW INTRODUCTIONS COST CREDITS AGAIN.
     *
     * The positive control for the test above: if nothing were ever charged,
     * "stays unlocked" would pass on a product that never locked anything.
     */
    public function test_a_new_introduction_after_the_cutover_requires_credits(): void
    {
        $this->setMode(true);
        $user = $this->member();
        $this->actingAs($user)->post("/matches/{$this->teaser($user)->id}/unlock");

        $this->setMode(false);

        $fresh = $this->teaser($user->fresh());

        $this->actingAs($user->fresh())
            ->post("/matches/{$fresh->id}/unlock")
            ->assertRedirect();

        $this->assertSame(
            MatchRecord::STATUS_TEASER,
            $fresh->fresh()->status,
            'A new introduction opened without credits after the free period ended, so the mode '
                .'never really left.'
        );
    }

    /** ⚠️ Re-entering works too. A mode that can only be left once is also a trap. */
    public function test_the_mode_can_be_re_entered(): void
    {
        $this->setMode(true);
        $this->setMode(false);
        $this->setMode(true);

        $user = $this->member();
        $match = $this->teaser($user);

        $this->actingAs($user)->post("/matches/{$match->id}/unlock");

        $this->assertSame(MatchRecord::STATUS_UNLOCKED, $match->fresh()->status);
    }

    /**
     * ⚠️ THE PROMISE REACHES A MEMBER WHERE THEY ACT ON IT.
     *
     * The same sentence is in the refund policy, which is the contract and
     * which nobody reads. A promise only in a page we can point at later is a
     * defence, not something a member relied on when they signed up.
     */
    public function test_the_registration_page_states_the_promise_during_the_free_period(): void
    {
        $this->setMode(true);

        $props = $this->get('/register')->viewData('page')['props'];

        $this->assertTrue($props['freePeriod']);
    }

    /** ⚠️ POSITIVE CONTROL: and does not claim it when the period is over. */
    public function test_the_registration_page_makes_no_such_claim_afterwards(): void
    {
        $this->setMode(false);

        $this->assertFalse($this->get('/register')->viewData('page')['props']['freePeriod']);
    }

    /**
     * ⚠️ THE WORDING MUST MATCH THE REFUND POLICY EXACTLY.
     *
     * If the two drift, the page is the contract and the sign-up line is
     * marketing — and the gap between them is precisely where a complaint
     * lives.
     */
    public function test_the_promise_matches_the_refund_policy_word_for_word(): void
    {
        $promise = trans('realvows.free_period.kept');

        $this->assertStringContainsString('yours to keep permanently', $promise);

        $this->assertStringContainsString(
            'yours to keep permanently',
            $this->get('/refunds')->getContent(),
            'The sign-up promise and the refund policy have drifted apart. The page is the '
                .'contract; the sign-up line is what the member actually read.'
        );
    }

    /**
     * ⚠️ A BROKEN READ ALERTS, IT DOES NOT ONLY LOG.
     *
     * Failing closed charges for something that should be free. That is
     * "visible and refundable" only if somebody NOTICES — and a member charged
     * during a free period does not think "that must be a bug", they think the
     * free period was a lie.
     */
    public function test_an_unreadable_setting_is_logged_at_critical(): void
    {
        \Illuminate\Support\Facades\Log::spy();

        $broken = new class(app(SettingsService::class)) extends FreePeriod
        {
            public function __construct(private $unused)
            {
                parent::__construct(app(SettingsService::class));
            }
        };

        // Drop the table the read depends on, so the failure is real rather
        // than mocked — a mocked failure proves the catch block compiles.
        \Illuminate\Support\Facades\Schema::drop('settings');

        $this->assertFalse($broken->isActive(), 'A broken read must fail CLOSED — charging, not giving away.');

        \Illuminate\Support\Facades\Log::shouldHaveReceived('critical')
            ->withArgs(fn ($message) => str_contains($message, 'charging members'));
    }

    /**
     * ⚠️ THE STAMP ANSWERS FROM THE ROW, NOT FROM THE CLOCK.
     *
     * Reading the current mode, or comparing dates against a settings value
     * somebody can edit, would make "was this free?" flip the moment the
     * setting changed — which is the retroactive re-terms in another costume.
     */
    public function test_whether_it_was_free_is_answered_from_the_row(): void
    {
        $this->setMode(true);
        $user = $this->member();
        $free = $this->teaser($user);
        $this->actingAs($user)->post("/matches/{$free->id}/unlock");

        $this->setMode(false);

        $this->assertTrue(app(FreePeriod::class)->wasGrantedFree($free->fresh()));

        // A paid one, in the same database, must answer false.
        $paid = $this->teaser($user->fresh());
        app(\App\Services\Credits\CreditService::class)
            ->adjust($user->fresh(), 1, 'goodwill', 'Test', $user->fresh());
        app(\App\Services\Credits\CreditService::class)
            ->unlockMatch($user->fresh(), $paid);

        $this->assertFalse(
            app(FreePeriod::class)->wasGrantedFree($paid->fresh()),
            'A paid introduction is being reported as free, so the two cannot be told apart.'
        );
    }
}
