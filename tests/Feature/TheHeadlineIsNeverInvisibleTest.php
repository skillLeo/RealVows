<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE HOME PAGE HEADLINE WENT MISSING ON THE LIVE SITE, AND ONLY ON A
 * FIRST VISIT.
 *
 * The client: "see text not visible due to wrong color combo." It was not a
 * colour choice. Two features that are each correct alone destroy each other
 * when they meet:
 *
 *  · **The Gleam** paints a gradient on the h1 and clips it to the glyphs.
 *    That requires `color: transparent` — the text is a stencil, and the
 *    gradient behind it is the only thing anybody ever sees.
 *
 *  · **The arrival sequence** replaces the h1's text with `.rv-word` spans so
 *    each word can fade in. `.rv-word` is `display: inline-block`, and an
 *    inline-block descendant paints its own text in its own box. The parent's
 *    clip never reaches it, the spans inherit `transparent`, and they have no
 *    background of their own to be clipped.
 *
 * Result: a stencil with nothing behind it. The headline occupied its full
 * height and painted nothing.
 *
 * ⚠️ AND IT HID BEHIND sessionStorage. The arrival runs once per session, so
 * the second load never splits the text and the clip works perfectly. Invisible
 * cold, correct on refresh — which reads exactly like a flash of unstyled
 * content and is nothing of the kind. It survived a local "fix" and reached the
 * client's live home page because a reload is the first thing anybody does.
 *
 * ── ⚠️ WHY THIS IS A SOURCE TEST AND NOT A BROWSER TEST ──────────────────
 *
 * A browser test would be the better proof and would need a real Chrome in CI.
 * This asserts the two things that made it possible, both of which are visible
 * in the source and neither of which can drift back silently.
 */
class TheHeadlineIsNeverInvisibleTest extends TestCase
{
    private function css(): string
    {
        return File::get(base_path('resources/css/app.css'));
    }

    /**
     * ⚠️ TRANSPARENT TEXT IS ONLY EVER SAFE WHEN SOMETHING IS CLIPPED BEHIND
     * IT, AND ONLY WHILE THE TEXT IS THE ELEMENT'S OWN.
     */
    public function test_the_gleam_cannot_apply_to_a_headline_split_into_words(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(
            '.rv-hero .rv-d1:not(:has(.rv-word))',
            $css,
            'The Gleam is no longer scoped away from a headline that has been split into '
                .'`.rv-word` spans. Those spans are inline-block, so the h1 background-clip '
                .'cannot reach their glyphs, and the headline paints nothing at all on a '
                .'first visit. This is the exact bug the client reported on the live site.',
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL. The assertion above is a substring check, and a
     * substring check passes just as happily against a stylesheet where the
     * whole rule has been deleted, renamed, or moved somewhere it no longer
     * runs. This proves the two halves it is guarding are both still present.
     */
    public function test_the_gleam_is_actually_still_there_to_be_guarded(): void
    {
        $css = $this->css();

        $this->assertStringContainsString(
            '-webkit-text-fill-color: transparent',
            $css,
            'Nothing in the stylesheet makes text transparent any more, so the guard above '
                .'is protecting a feature that no longer exists and would pass forever.',
        );

        $this->assertStringContainsString(
            '.rv-word { display: inline-block',
            $css,
            'The word spans are no longer inline-block. That is what breaks the parent clip, '
                .'so if it has changed the guard above may be solving a problem that moved.',
        );
    }

    /**
     * ⚠️ THE SPANS MUST NOT OUTLIVE THE ANIMATION.
     *
     * The CSS guard makes the failure safe — a headline with no gleam rather
     * than no headline. This is the other half: putting the sentence back means
     * the flourish the page was designed with actually runs, instead of being
     * permanently suppressed by leftover markup.
     */
    public function test_the_arrival_script_puts_the_sentence_back_together(): void
    {
        $script = File::get(base_path('resources/views/public/partials/arrival.blade.php'));

        $this->assertStringContainsString(
            'heading.textContent = whole',
            $script,
            'The arrival sequence no longer restores the headline after the words land, so '
                .'the `.rv-word` spans stay in the document forever and the Gleam is '
                .'permanently suppressed by the guard in app.css.',
        );
    }
}
