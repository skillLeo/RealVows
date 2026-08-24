<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ A BUTTON CARRIES ITS OWN CONTRAST. NOTHING MAY REACH PAST IT AND REPAINT IT.
 *
 * The client reported that "Create your free account" was unreadable on the
 * closing band: "the button text and background color is too much invisible".
 * He was reading a real number. It measured 2.07:1 against a 4.5 floor.
 *
 * Nothing was wrong with the button. `.rv-on-dark a` is a PROSE rule — it
 * colours links inside a paragraph on a dark panel — and at specificity (0,1,1)
 * it beat `.rv-btn--on-dark` at (0,1,0). Every button placed on a dark panel
 * had its type repainted brass while keeping its own pale fill.
 *
 * ⚠️ THIS IS A CLASS OF BUG, NOT AN INCIDENT. A descendant selector that names
 * a bare element inside a themed container will swallow any component put
 * inside it, and it will do so silently: the rule looks correct, the component
 * looks correct, and only the rendered pair is wrong.
 */
class ButtonsSurviveTheirSurroundingsTest extends TestCase
{
    private function css(): string
    {
        return File::get(base_path('resources/css/app.css'));
    }

    /**
     * ⚠️ THE SPECIFIC REGRESSION, NAMED.
     *
     * If this rule loses its exclusion, every button on every dark panel goes
     * brass-on-pale again and no other test in the suite notices.
     */
    public function test_the_dark_panel_link_colour_does_not_reach_buttons(): void
    {
        $css = $this->css();

        preg_match_all('/^\s*(\.rv-on-dark\s+a[^,{]*)/m', $css, $matches);

        $this->assertNotEmpty(
            $matches[1],
            'No `.rv-on-dark a` rule was found at all. Either it was renamed, in which case this '
                .'guard now protects nothing, or the dark panels have lost their link colour.',
        );

        foreach ($matches[1] as $selector) {
            $this->assertStringContainsString(
                ':not(.rv-btn)',
                $selector,
                "`{$selector}` repaints every link inside a dark panel, buttons included. It is "
                    .'higher specificity than the button variants, so a button keeps its own fill '
                    .'and loses its own text colour. Exclude .rv-btn.',
            );
        }
    }

    /**
     * ⚠️ NO HOVER STATE MAY INTRODUCE BRASS ON A CONTROL.
     *
     * Every button hover on the public pages used to turn gold, and two of them
     * fell under AA doing it — burgundy type on a brass wash measured 4.01:1.
     * A hover is a step, not a costume change: it says "this is the thing under
     * your cursor" and changes nothing else.
     */
    public function test_no_button_hover_fills_itself_with_brass(): void
    {
        $css = $this->css();

        preg_match_all('/(\.rv-btn[^{}]*:(?:hover|focus-visible)[^{}]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);

        $this->assertNotEmpty($rules, 'No button hover rules were read, so this proves nothing.');

        $offences = [];

        foreach ($rules as [, $selector, $body]) {
            /*
             * ⚠️ THE BORDER COUNTS, NOT JUST THE FILL. The first version of
             * this guard read `background` only and passed a primary button
             * whose border jumped from burgundy to gold on hover. A one-pixel
             * hue change is still a hue change, and it was the last brass left
             * on any control.
             */
            foreach (['background(?:-color)?', 'border(?:-color)?'] as $property) {
                if (! preg_match('/(?<![a-z-])'.$property.'\s*:\s*([^;]+)/', $body, $value)) {
                    continue;
                }

                if (preg_match('/--brass(?!-fade)/', $value[1])) {
                    $offences[] = trim($selector).' -> '.trim($value[1]);
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A button hover fills itself with brass:\n  ".implode("\n  ", $offences)
                ."\nHovers step the grey or deepen the burgundy. Gold on a control was both the "
                .'messiest and the least legible thing on the page.',
        );
    }
}
