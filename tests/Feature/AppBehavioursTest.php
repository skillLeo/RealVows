<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE TWO BEHAVIOURS THAT WERE ASSUMED DONE AND WERE NOT.
 *
 * An audit of Addendum A Part E found seven of nine app behaviours built and
 * two merely believed in. One turned out to be genuinely correct and the other
 * was a component that existed, looked finished in a file listing, and was
 * referenced by nothing — the same failure as the copy deck.
 */
class AppBehavioursTest extends TestCase
{
    private function behaviours(): string
    {
        return File::get(resource_path('js/app-behaviours.js'));
    }

    /**
     * ⚠️ CREDITS ALWAYS WAIT FOR THE SERVER. This is the one exception to
     * optimistic UI in the whole product.
     *
     * Everything else may assume success and correct itself. A credit may not:
     * showing a member a balance of 2 that turns out to be 3, or an
     * introduction that appears open and then closes, is a lie about money.
     * The balance is rendered from server props and a change requires a round
     * trip, so there is no local mutation that could disagree with the ledger.
     */
    public function test_the_credit_balance_is_never_optimistic(): void
    {
        $dashboard = File::get(resource_path('js/Pages/Dashboard.vue'));

        $this->assertMatchesRegularExpression(
            '/router\.post\(`\/matches\/\$\{id\}\/unlock`/',
            $dashboard,
            'Unlocking no longer goes through a server round trip.'
        );

        /*
         * The give-away for the regression: the balance being adjusted in the
         * browser before the server has agreed.
         */
        foreach (['credits--', 'credits -=', 'credits.value--', 'balance--', 'balance -='] as $mutation) {
            $this->assertStringNotContainsString(
                $mutation,
                $dashboard,
                "The credit balance is being changed locally (\"{$mutation}\") before the server "
                    .'has confirmed it. Every other optimistic update in this product is safe to '
                    .'correct; a balance that flickers between two numbers is a lie about money.'
            );
        }
    }

    /**
     * ⚠️ THREE TIERS, AND THE FIRST ONE IS NOTHING.
     *
     * Under 300ms a loading indicator is worse than none — it appears and
     * vanishes as a flash, which reads as a glitch rather than as speed.
     */
    public function test_loading_has_three_tiers_and_shows_nothing_under_300ms(): void
    {
        $js = $this->behaviours();

        $this->assertStringContainsString(
            'threeTierLoading',
            $js,
            'Three-tier loading is gone. The skeleton component existed for weeks with zero '
                .'references — finished in a file listing and never once on screen.'
        );

        // Tier 1 → 2: the skeleton waits 300ms.
        $this->assertMatchesRegularExpression(
            '/skeletonTimer = setTimeout\((?:[^;]|;)*?\}, 300\);/s',
            $js,
            'The skeleton no longer waits 300ms, so fast navigations flash a loading state.'
        );

        /*
         * Tier 3: an honest line, not a spinner and not a percentage.
         *
         * ⚠️ SCOPED TO THE RENDERED STRING, NOT THE FILE. Run against the
         * whole source this reads the comment above threeTierLoading, which
         * quotes the very phrases it bans — the guard fails on its own safety
         * rail rather than on a breach. Exactly the trap CopyDeckTest hit
         * with the word "calculated".
         */
        preg_match('/line\.textContent = \'([^\']+)\'/', $js, $sentence);

        $this->assertNotEmpty($sentence, 'The slow-loading line is no longer a plain string.');
        $this->assertStringContainsString('This is taking longer than usual', $sentence[1]);
        $this->assertStringNotContainsStringIgnoringCase('almost there', $sentence[1]);
        $this->assertStringNotContainsString('%', $sentence[1]);
    }

    /** And it is actually started, not merely defined. */
    public function test_three_tier_loading_is_started(): void
    {
        $js = $this->behaviours();

        $bootAt = strpos($js, 'export function startAppBehaviours');
        $this->assertNotFalse($bootAt, 'The behaviours entry point has gone.');

        $this->assertStringContainsString(
            'threeTierLoading();',
            substr($js, $bootAt),
            'threeTierLoading is defined but never called — which is exactly the state the '
                .'skeleton component was already in.'
        );
    }

    /** The tiers need somewhere to render. */
    public function test_the_loading_panel_is_styled(): void
    {
        $css = File::get(resource_path('css/app.css'));

        $this->assertStringContainsString('.rv-loading {', $css);
        $this->assertStringContainsString('.rv-loading__line', $css);

        // It reuses The Shimmer rather than inventing a second one.
        $this->assertStringContainsString('.rv-skeleton {', $css);
        $this->assertMatchesRegularExpression('/\.rv-skeleton\s*\{[^}]*rv-shimmer/s', $css);
    }

    /**
     * ⚠️ The other seven behaviours, asserted so an audit never has to guess
     * again. Each is one line here and was a paragraph of uncertainty before.
     */
    public function test_the_other_app_behaviours_are_present(): void
    {
        $js = $this->behaviours();

        foreach ([
            'scrollRestoration' => 'scroll position surviving a back press',
            'popstate' => 'the back-button contract',
            'toast' => 'queued toasts',
            'pullToRefresh' => 'pull to refresh',
            'longPress' => 'long press on a match card',
            'themeColour' => 'the status bar colour following the route',
        ] as $needle => $what) {
            $this->assertStringContainsString(
                $needle,
                $js,
                "The behaviour for {$what} has gone."
            );
        }

        // Route-level code splitting: a member must never download admin JS.
        $this->assertStringContainsString(
            "import.meta.glob('./Pages/**/*.vue')",
            File::get(resource_path('js/app.js')),
            'Pages are no longer lazily resolved, so every member downloads the admin bundle.'
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). These are all presence checks over source
     * text, so the readers must be shown finding and not-finding correctly.
     */
    public function test_the_readers_can_see_what_they_claim_to(): void
    {
        // The 300ms reader finds a real delay and rejects a different one.
        $good = 'skeletonTimer = setTimeout(() => { build(); }, 300);';
        $bad = 'skeletonTimer = setTimeout(() => { build(); }, 0);';

        $this->assertMatchesRegularExpression('/skeletonTimer = setTimeout\((?:[^;]|;)*?\}, 300\);/s', $good);
        $this->assertDoesNotMatchRegularExpression('/skeletonTimer = setTimeout\((?:[^;]|;)*?\}, 300\);/s', $bad);

        // The optimistic-balance reader sees a real local mutation.
        $this->assertStringContainsString('credits--', 'if (ok) credits--;');
    }
}
