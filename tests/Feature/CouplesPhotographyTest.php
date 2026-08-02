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
     * ⚠️ NEITHER PLACEMENT MAY HARDCODE A PATH.
     *
     * The hero renders the seven-tile mosaic and the couples section renders
     * the pair, so they no longer share one set — but both must still read
     * from a manifest rather than naming files in markup. A hardcoded path is
     * how a photograph ends up live with no focal point and no licence row.
     */
    public function test_both_placements_read_from_a_manifest(): void
    {
        $home = File::get(resource_path('views/public/home.blade.php'));
        $mosaic = File::get(resource_path('views/public/partials/hero-mosaic.blade.php'));
        $section = File::get(resource_path('views/public/partials/couples-rail.blade.php'));

        $this->assertStringContainsString(
            'Mosaic::tiles()',
            $mosaic,
            'The hero mosaic no longer reads from App\\Support\\Mosaic.'
        );

        $this->assertStringContainsString(
            'Rail::all()',
            $section,
            'The marquee section no longer reads from App\\Support\\Rail.'
        );

        // The hero crops live sources, so each needs its own focal point. The
        // marquee's files are pre-cropped to the tile ratio by the generator,
        // so there is nothing left to crop and one CSS rule covers all twelve.
        $this->assertStringContainsString(
            'object-position',
            $mosaic,
            'The hero mosaic renders a photograph without applying its focal point.'
        );

        // No hardcoded image path has crept into either.
        foreach (['images/site/hero-a.jpg', 'images/site/hero-b.jpg', 'couple-muslim.jpg'] as $path) {
            $this->assertStringNotContainsString($path, $home);
            $this->assertStringNotContainsString($path, $mosaic);
        }
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
     * ⚠️ THE CELL DEFINES THE RATIO. THE PHOTOGRAPH NEVER DOES.
     *
     * This is the direct descendant of the "distorted" complaint. The old
     * hero let a min-height drive the box and released its aspect ratio above
     * 880px, so two frames resolved to different heights and one photograph
     * was scaled differently from the other.
     *
     * On a grid the same failure has a different shape: a row sized `auto`
     * or a bare `1fr` takes its minimum from its content, so one tall image
     * silently opens its whole row and every tile beside it changes shape.
     * `minmax(0, 1fr)` is what forbids that, and it is easy to lose in a
     * refactor because the layout looks fine until a particular file lands.
     */
    public function test_no_photograph_can_change_the_shape_of_its_row(): void
    {
        $css = File::get(resource_path('css/app.css'));

        preg_match('/\.rv-mosaic\s*\{([^}]*)\}/', $css, $m);

        $this->assertNotEmpty($m, 'The .rv-mosaic grid rule has gone.');

        $this->assertStringContainsString(
            'minmax(0, 1fr)',
            $m[1],
            'The mosaic rows are no longer minmax(0, 1fr), so a single tall photograph can '
                .'open its row and reshape every tile beside it.'
        );

        $this->assertMatchesRegularExpression(
            '/aspect-ratio:\s*\d+\s*\/\s*\d+/',
            $m[1],
            'The mosaic has no aspect-ratio, so its row heights are no longer defined by the '
                .'grid and the source images decide the layout again.'
        );

        // A tile must never carry its own ratio — that is the image talking.
        $this->assertDoesNotMatchRegularExpression(
            '/\.rv-mosaic__tile[^{}]*\{[^}]*aspect-ratio:/',
            $css,
            'A mosaic tile declares its own aspect-ratio. Shape belongs to the grid; a tile '
                .'that sizes itself is how the frames drifted apart last time.'
        );

        /*
         * ⚠️ Dropped tiles must be display:none, not opacity or visibility.
         * A tile hidden with opacity still occupies its grid cell AND still
         * downloads its photograph — which is the opposite of the reason the
         * phone drops tiles at all.
         */
        /*
         * ⚠️ Read from the manifest, never listed here. This assertion used
         * to name tiles f and g literally and broke the moment an eighth tile
         * was added — which is the failure mode where a test teaches you to
         * edit the test.
         */
        $dropped = array_diff(
            array_keys(\App\Support\Mosaic::TILES),
            \App\Support\Mosaic::keysAt('mobile')
        );

        $this->assertNotEmpty($dropped, 'The phone no longer drops any tile.');

        foreach ($dropped as $key) {
            $this->assertMatchesRegularExpression(
                '/\.rv-mosaic__tile--'.$key.'[,\s][^{]*\{[^}]*display:\s*none/',
                $css,
                "Tile {$key} is dropped on mobile but is not display:none, so it still costs a "
                    .'download on the connection least able to afford one.'
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL for the check above. Every assertion in it is a
     * negative or a presence check, so it is worth nothing unless it can see
     * the real thing.
     */
    public function test_the_shape_check_recognises_the_css_that_actually_shipped(): void
    {
        // The rules that produced two different frame heights, verbatim.
        $broken = "@media (min-width: 880px) {\n"
            ."  .rv-hero__art { min-height: clamp(360px, 52vw, 680px); }\n"
            ."  .rv-hero__frame { aspect-ratio: auto; }\n"
            ."}\n"
            .'.rv-hero__frame--second { margin-top: clamp(20px, 5vw, 56px); }';

        $this->assertMatchesRegularExpression('/\.rv-hero__frame[^{}]*\{[^}]*aspect-ratio:\s*(auto|none)/', $broken);
        $this->assertMatchesRegularExpression('/\.rv-hero__art[^{}]*\{[^}]*min-height:/', $broken);

        // And the grid-shaped version of the same defect.
        $loose = '.rv-mosaic { grid-template-rows: repeat(3, 1fr); }'
            .'.rv-mosaic__tile--a { aspect-ratio: 3 / 4; }';

        $this->assertStringNotContainsString(
            'minmax(0, 1fr)',
            $loose,
            'The reader cannot tell a bare 1fr from a clamped one.'
        );

        $this->assertMatchesRegularExpression(
            '/\.rv-mosaic__tile[^{}]*\{[^}]*aspect-ratio:/',
            $loose,
            'The reader cannot see a tile that sizes itself.'
        );
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
