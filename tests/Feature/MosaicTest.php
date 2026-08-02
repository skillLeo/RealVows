<?php

namespace Tests\Feature;

use App\Support\Mosaic;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ THE MANIFEST CLAIMS TO BE THE SOURCE OF TRUTH FOR TILE PLACEMENT, BUT
 * THE BROWSER READS THE STYLESHEET.
 *
 * Three named layouts cannot be expressed in an inline style, so grid
 * placement has to live in CSS. That leaves the manifest describing something
 * it does not control — which is worse than not recording it at all, because
 * a reader trusts it. These tests make the two agree or fail.
 */
class MosaicTest extends TestCase
{
    private function css(): string
    {
        return File::get(resource_path('css/app.css'));
    }

    private function markup(): string
    {
        return File::get(resource_path('views/public/partials/hero-mosaic.blade.php'));
    }

    /**
     * The CSS inside the `@media (min-width: Npx)` block that holds the
     * mosaic, by brace matching — a regex cannot do this, because the block
     * contains nested rules.
     *
     * ⚠️ IT MUST BE THE MOSAIC'S BLOCK, NOT THE FIRST ONE AT THAT WIDTH.
     *
     * This stylesheet has several `@media (min-width: 768px)` blocks and the
     * mosaic's is not the first. Taking the first one made this reader report
     * the tablet placement as the mobile placement and fail a manifest that
     * was correct — the check accused the wrong file. Found because the
     * failure named a value that appeared nowhere in the mobile rules.
     */
    private function mediaBlock(string $css, int $minWidth): string
    {
        $needle = "@media (min-width: {$minWidth}px) {";
        $offset = 0;

        while (($start = strpos($css, $needle, $offset)) !== false) {
            $i = $start + strlen($needle);
            $depth = 1;

            while ($depth > 0 && $i < strlen($css)) {
                $depth += match ($css[$i]) { '{' => 1, '}' => -1, default => 0 };
                $i++;
            }

            $block = substr($css, $start, $i - $start);

            if (str_contains($block, '.rv-mosaic__tile--')) {
                return $block;
            }

            $offset = $i;
        }

        $this->fail("No @media (min-width: {$minWidth}px) block contains mosaic tile rules.");
    }

    /** Every `grid-area` for mosaic tiles in a chunk of CSS, keyed by tile. */
    private function areasIn(string $css): array
    {
        preg_match_all(
            '/\.rv-mosaic__tile--([a-h])\s*\{[^}]*grid-area:\s*([^;}]+)/',
            $css,
            $m,
            PREG_SET_ORDER
        );

        $areas = [];

        foreach ($m as $hit) {
            $areas[$hit[1]] = trim($hit[2]);
        }

        return $areas;
    }

    /** ⚠️ THE CHECK THIS CLASS EXISTS FOR. */
    public function test_the_stylesheet_places_every_tile_where_the_manifest_says(): void
    {
        foreach (['mobile' => null, 'tablet' => 768, 'desktop' => 1024] as $name => $minWidth) {
            $this->assertPlacedAt($name, $minWidth);
        }
    }

    private function assertPlacedAt(string $name, ?int $minWidth): void
    {
        $css = $this->css();

        if ($minWidth === null) {
            // Mobile rules sit outside any media query, so strip the two
            // wider blocks out before reading.
            $scope = str_replace(
                [$this->mediaBlock($css, 768), $this->mediaBlock($css, 1024)],
                '',
                $css
            );
        } else {
            $scope = $this->mediaBlock($css, $minWidth);
        }

        $actual = $this->areasIn($scope);

        foreach (Mosaic::TILES as $key => $tile) {
            $expected = $tile['grid'][$name];

            if ($expected === null) {
                continue;
            }

            $this->assertArrayHasKey(
                $key,
                $actual,
                "Tile {$key} is placed at {$name} in the manifest but the stylesheet never "
                    .'positions it there, so it will inherit the previous breakpoint and overlap.'
            );

            $this->assertSame(
                $expected,
                $actual[$key],
                "Tile {$key} at {$name}: the manifest says [{$expected}] and the stylesheet "
                    ."says [{$actual[$key]}]. One of them is lying to whoever reads it next."
            );
        }
    }

    /**
     * ⚠️ A DROPPED TILE MUST BE DROPPED, NOT SHRUNK.
     *
     * Seven tiles at 375px puts each face in about 150px, which no face
     * survives. The phone drops two, and it must drop exactly the two the
     * manifest names — dropping a different pair would silently change which
     * religion signals a mobile visitor sees, and most visitors are on a
     * phone.
     */
    public function test_the_phone_drops_exactly_the_tiles_the_manifest_drops(): void
    {
        $this->assertSame(
            ['a', 'b', 'c', 'd', 'e'],
            Mosaic::keysAt('mobile'),
            'The set of tiles shown on a phone has changed.'
        );

        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f'], Mosaic::keysAt('tablet'));
        $this->assertSame(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'], Mosaic::keysAt('desktop'));

        // And the balance survives the drop: a phone still shows both signals.
        $onPhone = array_map(fn ($k) => Mosaic::TILES[$k]['signal'], Mosaic::keysAt('mobile'));

        $this->assertContains('muslim', $onPhone, 'A phone shows no visibly Muslim couple.');
        $this->assertContains('christian', $onPhone, 'A phone shows no visibly Western couple.');
    }

    /**
     * ⚠️ DUPLICATE PHOTOGRAPHY READS AS FAKE, and it has been caught in this
     * project once already.
     *
     * While stand-ins are in force every tile deliberately repeats the two
     * approved photographs, so the real check cannot run yet. It is deferred
     * LOUDLY rather than passing quietly — a guard that skips in silence is
     * indistinguishable from one that works.
     */
    public function test_no_two_tiles_use_the_same_photograph(): void
    {
        $files = array_column(Mosaic::TILES, 'file');

        $this->assertSame(
            $files,
            array_unique($files),
            'Two tiles point at the same file. Seven tiles showing five photographs reads as '
                .'a padded stock library, which is exactly what the mosaic is meant not to be.'
        );

        if (Mosaic::awaitingRealPhotography()) {
            $rendered = array_column(Mosaic::tiles(), 'src');
            $real = array_filter($rendered);

            $this->assertLessThan(
                count($files),
                count(array_unique($real)),
                'Stand-ins are supposed to be in force but every tile already has a distinct '
                    .'photograph — the placeholder flag is stale.'
            );

            $this->markTestIncomplete(
                'DEFERRED: '.count(array_unique($real)).' distinct photographs are standing in '
                    .'across '.count($files).' tiles. The real distinctness check runs once the '
                    .'files land. This is expected while the composition is being approved.'
            );
        }
    }

    /** Every tile carries a hand-set focal point, and none is dead centre. */
    public function test_every_tile_has_a_deliberate_focal_point(): void
    {
        foreach (Mosaic::TILES as $key => $tile) {
            $this->assertMatchesRegularExpression(
                '/^\d{1,3}% \d{1,3}%$/',
                $tile['focal'],
                "Tile {$key} has no usable focal point."
            );

            $this->assertNotSame(
                '50% 50%',
                $tile['focal'],
                "Tile {$key} uses dead centre, which is what `cover` already does and therefore "
                    .'means nobody looked at the photograph.'
            );
        }
    }

    /**
     * ⚠️ ONE LABEL FOR THE WHOLE MOSAIC, SILENT PARTS.
     *
     * Seven decorative photographs with empty alt and no wrapper tells a
     * screen reader user nothing is there. Seven descriptive alts is worse in
     * practice — it reads seven near-identical sentences aloud before the
     * visitor reaches the sign-up button.
     */
    public function test_the_mosaic_is_announced_once_and_its_tiles_are_silent(): void
    {
        $markup = $this->markup();

        $this->assertMatchesRegularExpression(
            '/class="rv-mosaic"\s+role="img"/',
            $markup,
            'The mosaic is no longer a single labelled image.'
        );

        $this->assertStringContainsString('aria-label="Couples who met through RealVows"', $markup);

        $this->assertMatchesRegularExpression(
            '/\balt=""/',
            $markup,
            'The tiles inside the mosaic must have empty alt — the wrapper does the announcing.'
        );

        // No tile may reintroduce a description.
        $this->assertDoesNotMatchRegularExpression(
            '/alt="\{\{/',
            $markup,
            'A tile has a dynamic alt again, so a screen reader will read seven near-identical '
                .'sentences before reaching the call to action.'
        );
    }

    /**
     * ⚠️ A 160px TILE MUST NEVER DOWNLOAD A 1400px FILE.
     *
     * Seven photographs on a hero is a real budget question on Caribbean 4G,
     * and `sizes` is the only thing that stops the browser guessing 100vw for
     * every one of them.
     */
    public function test_small_tiles_do_not_ask_for_large_files(): void
    {
        foreach (Mosaic::tiles() as $tile) {
            preg_match('/\(min-width: 1024px\) (\d+)px/', $tile['sizes'], $m);

            $this->assertNotEmpty($m, "Tile {$tile['key']} has no desktop size hint.");

            /*
             * No tile in the arcade renders wider than ~290px at any
             * breakpoint. A hint above that means somebody changed the
             * layout and left the size hint behind, and the browser will
             * quietly fetch a file several times larger than the box.
             */
            $this->assertLessThanOrEqual(
                320,
                (int) $m[1],
                "Tile {$tile['key']} claims to render at {$m[1]}px on desktop, which no tile in "
                    .'the arcade does. The size hint has drifted from the layout.'
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL. The placement test is a comparison, so it is only
     * worth something if a mismatch actually fails it.
     */
    public function test_the_placement_reader_can_see_a_mismatch(): void
    {
        $good = '.rv-mosaic__tile--a { grid-area: 1 / 1 / 3 / 3; }';
        $this->assertSame(['a' => '1 / 1 / 3 / 3'], $this->areasIn($good));

        // A tile the stylesheet never places is invisible to the reader.
        $this->assertSame([], $this->areasIn('.rv-mosaic__tile--a { opacity: 1; }'));

        // And the media-block reader must actually scope.
        $css = $this->css();
        $desktop = $this->mediaBlock($css, 1024);

        $this->assertStringContainsString('21fr 31fr 26fr 22fr', $desktop);
        $this->assertStringNotContainsString('repeat(2, minmax(0, 1fr))', $desktop);
    }
}
