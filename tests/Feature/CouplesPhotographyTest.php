<?php

namespace Tests\Feature;

use App\Support\Couples;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ A PHOTOGRAPH SWAPPED WITHOUT RE-CHECKING ITS FOCAL POINT SILENTLY
 * REINTRODUCES A BUG NOTHING ELSE CAN SEE.
 *
 * The original failure: two photographs of different proportions cropped
 * into fixed cards with the default `center`, which has no idea where the
 * couple is in a given frame. It cut the top of a groom's headwear off on a
 * phone. Nothing overflowed, nothing errored, no layout was invalid — it was
 * simply wrong at a size a real device shows, and only eyes catch that.
 *
 * What CAN be enforced mechanically is that the deliberate decision is
 * present at all: every photograph carries a hand-set focal point, both
 * placements use the same pair, and nobody quietly goes back to `center`.
 */
class CouplesPhotographyTest extends TestCase
{
    /** ⚠️ The client's decision. Not a placeholder count. */
    public function test_there_are_exactly_two_couples(): void
    {
        $this->assertCount(
            2,
            Couples::all(),
            'This product shows exactly two couples — one Muslim, one Christian. '
                .'A wider spread reads as a stock library.'
        );

        $this->assertSame(['muslim', 'christian'], array_column(Couples::all(), 'key'));
    }

    public function test_every_photograph_carries_a_hand_set_focal_point(): void
    {
        foreach (Couples::all() as $couple) {
            $this->assertMatchesRegularExpression(
                '/^\d{1,3}% \d{1,3}%$/',
                $couple['focal'],
                "[{$couple['key']}] has no usable focal point. Set one by looking at where the "
                    .'couple actually sits in that photograph — see CREDITS.md.'
            );

            // `50% 50%` is what `center` already does, so it is the one value
            // that means "nobody looked".
            $this->assertNotSame(
                '50% 50%',
                $couple['focal'],
                "[{$couple['key']}] uses dead centre, which is the default and means nobody "
                    .'checked where the faces are.'
            );
        }
    }

    /**
     * Faces sit high in a wedding portrait. A vertical focal point below the
     * midpoint crops the tops of heads off, which is precisely the reported
     * bug.
     */
    public function test_no_focal_point_sits_below_the_middle_of_the_frame(): void
    {
        foreach (Couples::all() as $couple) {
            [, $vertical] = explode(' ', $couple['focal']);
            $vertical = (int) rtrim($vertical, '%');

            $this->assertLessThanOrEqual(
                45,
                $vertical,
                "[{$couple['key']}] focuses {$vertical}% down the frame. Faces sit high in a "
                    .'wedding portrait, so anything below the middle crops heads off on a narrow screen.'
            );
        }
    }

    /**
     * ⚠️ ONE PAIR, USED EVERYWHERE. Two sets would be two licences to
     * maintain and two slightly different stories on one page.
     */
    public function test_the_hero_and_the_couples_section_use_the_same_pair(): void
    {
        $home = File::get(resource_path('views/public/home.blade.php'));
        $section = File::get(resource_path('views/public/partials/couples-rail.blade.php'));

        foreach (['home hero' => $home, 'couples section' => $section] as $where => $markup) {
            $this->assertStringContainsString(
                'Couples::all()',
                $markup,
                "The {$where} no longer reads from the shared pair, so the two placements can drift apart."
            );

            $this->assertStringContainsString(
                'object-position',
                $markup,
                "The {$where} renders a photograph without applying its focal point."
            );
        }

        // And no hardcoded image path has crept back in beside it.
        $this->assertStringNotContainsString('images/site/hero-a.jpg', $home);
        $this->assertStringNotContainsString('images/site/hero-b.jpg', $home);
    }

    /**
     * ⚠️ hero-b.jpg shows a couple in Hindu/Sikh wedding dress and is a
     * stand-in only. This fails the moment somebody treats it as final.
     */
    public function test_the_placeholder_is_flagged_while_it_is_still_in_use(): void
    {
        $credits = File::get(public_path('images/site/CREDITS.md'));

        if (Couples::awaitingRealPhotography()) {
            $this->assertStringContainsString(
                'hero-b.jpg',
                $credits,
                'A placeholder photograph is in use but CREDITS.md does not say so.'
            );

            $this->assertMatchesRegularExpression(
                '/must (not ship|be replaced)/i',
                $credits,
                'CREDITS.md does not state that the placeholder must be replaced.'
            );
        } else {
            // Once real files land, the licence rows must be filled in.
            $this->assertStringNotContainsString(
                'not recorded',
                substr($credits, strpos($credits, '## The two couples') ?: 0),
                'The real photographs are in place but their licence is still unrecorded.'
            );
        }
    }

    /**
     * ⚠️ THE MUSLIM SLOT MUST NEVER FALL BACK TO THE HINDU PHOTOGRAPH.
     *
     * `hero-b.jpg` shows a couple in Hindu wedding dress — a red turban with
     * a kalgi, and a swastika on the bride's lehenga. On a product built for
     * Muslim and Christian members that is not a neutral stand-in; it is the
     * wrong statement made on the first screen anybody sees.
     *
     * A slot with no correct photograph renders a sealed panel. An obviously
     * empty frame says "not finished". A wrong photograph says something
     * about who this product is for, confidently and incorrectly.
     */
    public function test_the_muslim_slot_never_falls_back_to_the_wrong_couple(): void
    {
        $muslim = collect(Couples::all())->firstWhere('key', 'muslim');

        $this->assertNotNull($muslim);

        if ($muslim['isPlaceholder']) {
            $this->assertNull(
                $muslim['src'],
                'The Muslim slot is falling back to a photograph. There is no correct stand-in '
                    .'for it — hero-b.jpg shows a Hindu couple — so it must render a sealed panel.'
            );
        }

        // And no page may reference that file any more.
        foreach (['views/public/home.blade.php', 'views/public/partials/couples-rail.blade.php'] as $view) {
            $this->assertStringNotContainsString(
                'hero-b.jpg',
                File::get(resource_path($view)),
                "{$view} still references hero-b.jpg."
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. Every assertion above is about absence or shape,
     * so this proves the reader can actually see what it claims to check.
     */
    public function test_the_checks_can_actually_read_the_pair(): void
    {
        $all = Couples::all();

        $this->assertNotEmpty($all);
        $this->assertArrayHasKey('focal', $all[0]);
        $this->assertArrayHasKey('src', $all[0]);

        // `src` is deliberately nullable: a slot with no correct photograph
        // renders a sealed panel rather than the wrong couple.
        foreach ($all as $couple) {
            if ($couple['src'] !== null) {
                $this->assertStringStartsWith('images/site/', $couple['src']);
            }
        }

        // The focal-point regex would reject a bad value.
        $this->assertDoesNotMatchRegularExpression('/^\d{1,3}% \d{1,3}%$/', 'center');
        $this->assertMatchesRegularExpression('/^\d{1,3}% \d{1,3}%$/', '50% 28%');
    }
}
