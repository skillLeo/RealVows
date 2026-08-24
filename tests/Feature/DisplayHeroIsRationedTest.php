<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ THE ONE DISPLAY SIZE ADDED SINCE THE SCALE WAS LOCKED, AND THE CONDITIONS
 * ARE PART OF THE TOKEN RATHER THAN A NOTE BESIDE IT.
 *
 * The locked scale is 56 / 40 / 28. At 56 the home page hero reads as a heading
 * rather than as a statement, which is the single thing the client's reference
 * does better than us. So `--fs-display-hero` exists, and it is rationed:
 * public marketing routes only, once per page, on the hero line.
 *
 * ⚠️ WITHOUT THE RATION IT IS NOT AN EXCEPTION, IT IS A FOURTH SIZE. Used
 * twice on a page it stops being a statement and becomes a heading level, and
 * the next person reasonably reads it as available.
 */
class DisplayHeroIsRationedTest extends TestCase
{
    /** Every Blade template a public page can render. */
    private function publicTemplates(): array
    {
        // ⚠️ Through ScannedFiles, so a wrong path fails loudly instead of
        // reporting a spotless set of nothing.
        return ScannedFiles::of(
            File::allFiles(base_path('resources/views/public')),
            'public Blade templates',
            5,
        );
    }

    /** Everything a signed-in member or staff member sees. */
    private function authenticatedSources(): array
    {
        return array_merge(
            ScannedFiles::of(File::allFiles(base_path('resources/js/Pages')), 'member and staff pages', 10),
            ScannedFiles::of(File::allFiles(base_path('resources/js/Layouts')), 'layouts', 1),
        );
    }

    public function test_no_authenticated_screen_uses_the_hero_size(): void
    {
        $offences = [];

        foreach ($this->authenticatedSources() as $file) {
            $source = File::get($file);

            if (str_contains($source, 'rv-d-hero') || str_contains($source, 'fs-display-hero')) {
                $offences[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame(
            [],
            $offences,
            "The hero size reached an authenticated screen:\n  ".implode("\n  ", $offences)
                ."\n\nIt belongs to marketing, in the same family as The Gleam and The Ticker. A "
                .'member trying to finish a task does not need a 76px sentence.',
        );
    }

    public function test_no_public_page_uses_it_more_than_once(): void
    {
        foreach ($this->publicTemplates() as $file) {
            $source = preg_replace('#\{\{--.*?--\}\}#s', '', File::get($file)) ?? '';
            $uses = substr_count($source, 'rv-d-hero');

            $this->assertLessThanOrEqual(
                1,
                $uses,
                str_replace(base_path().'/', '', $file)." uses the hero size {$uses} times. Used "
                    .'more than once it is a heading level rather than a statement, which is a '
                    .'fourth display size arriving without anybody deciding to add one.',
            );
        }
    }

    /**
     * ⚠️ POSITIVE CONTROL, BOTH WAYS. Both assertions above are satisfied by a
     * token nobody uses at all, and by a class name that has been renamed out
     * from under them.
     */
    public function test_the_size_exists_and_is_actually_used_once(): void
    {
        $css = File::get(base_path('resources/css/app.css'));

        $this->assertStringContainsString(
            '--fs-display-hero',
            $css,
            'The token is gone, so both rations above are guarding nothing.',
        );

        $used = 0;

        foreach ($this->publicTemplates() as $file) {
            $used += substr_count(preg_replace('#\{\{--.*?--\}\}#s', '', File::get($file)) ?? '', 'rv-d-hero');
        }

        $this->assertSame(
            1,
            $used,
            "The hero size is used {$used} times across every public template. It is meant to "
                .'appear exactly once, on the home page hero.',
        );
    }

    /** ⚠️ The counter must be able to count. */
    public function test_the_counter_would_catch_a_second_use(): void
    {
        $fixture = '<h1 class="rv-d-hero">One</h1><h2 class="rv-d-hero">Two</h2>';

        $this->assertSame(2, substr_count($fixture, 'rv-d-hero'));
        $this->assertSame(0, substr_count('<h1 class="rv-d1">One</h1>', 'rv-d-hero'));
    }
}
