<?php

namespace Tests\Feature;

use App\Support\Rail;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ A MARQUEE HAS EXACTLY ONE WAY TO BE SEAMLESS, AND EVERY OTHER WAY LOOKS
 * BROKEN ONCE PER CYCLE.
 *
 * The track holds two identical copies of the set and translates by exactly
 * -50%. At that distance the start of the second copy lands precisely where
 * the first began, so the reset is invisible. Any other distance, an odd
 * number of copies, or a `gap` on the track rather than inside each copy,
 * produces a visible jump — and it appears once every sixty seconds, which is
 * long enough that nobody catches it in review.
 */
class RailTest extends TestCase
{
    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    private function markup(): string
    {
        return File::get(resource_path('views/public/partials/couples-rail.blade.php'));
    }

    /**
     * ⚠️ THE REASON THIS SECTION WAS FORBIDDEN MOTION UNTIL NOW.
     *
     * With two photographs the loop point lands inside the viewport and the
     * viewer watches the same face come round every few seconds. The old CSS
     * carried a standing note against a rail animation for exactly that
     * reason. The constraint was never "no motion" — it was "not enough
     * photographs", and this is the line between the two.
     */
    public function test_there_are_enough_photographs_for_the_loop_to_hide_itself(): void
    {
        $this->assertGreaterThanOrEqual(
            Rail::MINIMUM_FOR_A_SEAMLESS_LOOP,
            count(Rail::all()),
            'Too few photographs for a seamless marquee. Below this the repeat becomes visible '
                .'on a wide screen and the rail reads as a looping gif rather than as a gallery.'
        );

        $this->assertFalse(Rail::tooFewToScroll());
    }

    public function test_no_photograph_appears_twice_in_the_set(): void
    {
        $files = array_column(Rail::IMAGES, 'file');

        $this->assertSame(
            $files,
            array_unique($files),
            'The rail repeats a photograph within one pass. The set is rendered twice to make '
                .'the loop seamless, so a duplicate inside it shows up four times per cycle.'
        );
    }

    /** ⚠️ The three properties that make the loop invisible. */
    public function test_the_track_is_doubled_and_moves_by_exactly_half(): void
    {
        $markup = $this->markup();

        $this->assertStringContainsString(
            '@foreach ([false, true] as $isDuplicate)',
            $markup,
            'The track no longer renders two copies of the set, so the marquee will run out of '
                .'photographs and snap back.'
        );

        $this->assertMatchesRegularExpression(
            '/@keyframes rv-marquee-slide \{[\s\S]{0,300}?translate3d\(-50%/',
            $this->css(),
            'The marquee no longer translates by exactly -50%. At any other distance the loop '
                .'point does not line up and the reset becomes visible once per cycle.'
        );

        /*
         * ⚠️ The gap belongs INSIDE each copy, not on the track. A gap on the
         * track adds one extra space between the two copies, so -50% no
         * longer equals one full set and the seam drifts.
         */
        preg_match('/\.rv-marquee__track\s*\{([^}]*)\}/', $this->css(), $track);

        $this->assertNotEmpty($track, 'The marquee track rule has gone.');

        $this->assertStringNotContainsString(
            'gap:',
            $track[1],
            'The gap is on the marquee track. It must live inside each copy — on the track it '
                .'adds a space between the two copies, so -50% stops being exactly one set.'
        );
    }

    /**
     * ⚠️ WCAG 2.2.2. Anything that moves for more than five seconds needs a
     * way to stop it, and focus matters more than hover: a keyboard user
     * tabbing past a moving strip has no pointer to rest.
     */
    public function test_the_rail_can_be_stopped(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(':hover .rv-marquee__track', $css);
        $this->assertStringContainsString(':focus-within .rv-marquee__track', $css);
        $this->assertStringContainsString('animation-play-state: paused', $css);
    }

    /**
     * ⚠️ REDUCED MOTION MUST NOT HIDE PHOTOGRAPHS.
     *
     * Stopping the animation without giving the reader another way through
     * leaves ten of the twelve permanently off-screen for exactly the people
     * who asked for less movement. The rail becomes scrollable instead.
     */
    public function test_stopping_the_motion_does_not_hide_the_rest(): void
    {
        $css = $this->css();
        $start = strpos($css, '@media (prefers-reduced-motion: reduce)');

        // The reduced-motion block that mentions the marquee at all.
        while ($start !== false && ! str_contains(substr($css, $start, 700), 'rv-marquee')) {
            $start = strpos($css, '@media (prefers-reduced-motion: reduce)', $start + 1);
        }

        $this->assertNotFalse($start, 'Nothing stops the marquee under reduced motion.');

        $block = substr($css, $start, 700);

        $this->assertStringContainsString('animation: none', $block);

        $this->assertStringContainsString(
            'overflow-x: auto',
            $block,
            'The marquee stops under reduced motion but never becomes scrollable, so ten of the '
                .'twelve photographs can never be reached.'
        );
    }

    /**
     * ⚠️ The duplicate copy must be hidden from assistive technology, or a
     * screen reader is read the same twelve photographs twice.
     */
    public function test_the_duplicate_copy_is_silent(): void
    {
        $markup = $this->markup();

        $this->assertStringContainsString('@if ($isDuplicate) aria-hidden="true" @endif', $markup);
        $this->assertStringContainsString('role="img"', $markup);
        $this->assertMatchesRegularExpression('/\balt=""/', $markup);
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every check above is a presence or absence test on
     * source text, so this proves the readers can see what they claim to.
     */
    public function test_the_readers_can_see_a_broken_marquee(): void
    {
        // A track that moves by the wrong distance.
        $wrong = '@keyframes rv-marquee-slide { from { transform: translate3d(0,0,0); } '
            .'to { transform: translate3d(-100%, 0, 0); } }';

        $this->assertDoesNotMatchRegularExpression(
            '/@keyframes rv-marquee-slide \{[\s\S]{0,300}?translate3d\(-50%/',
            $wrong,
            'The reader cannot tell -50% from -100%, which is the difference between a seamless '
                .'loop and a visible snap.'
        );

        // And a gap in the wrong place.
        preg_match('/\.rv-marquee__track\s*\{([^}]*)\}/', '.rv-marquee__track { gap: 16px; }', $m);
        $this->assertStringContainsString('gap:', $m[1]);
    }
}
