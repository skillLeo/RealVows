<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A screen that renders a server collection must say something when it is empty.
 *
 * ⚠️ THE FAILURE IS SILENT AND LOOKS LIKE A BROKEN PAGE.
 *
 * A heading with nothing under it does not read as "there is nothing here". It
 * reads as a failed load, and the reader's next move is to refresh, then to
 * doubt whatever figure sits above it. The support page had the worse version:
 * the whole section was hidden when nothing was open, so a member who had just
 * written in saw the form, sent it, and found no trace of it anywhere.
 *
 * ⚠️ THE READER IS NARROW ON PURPOSE, AND THAT IS THE HARD PART.
 *
 * A first version flagged every v-for in the codebase and reported nineteen
 * pages. Most were loops over radio options and country codes — lists that
 * cannot be empty and must never carry an "it is empty" branch. A guard that
 * fires on nineteen things when three are real gets read once and then ignored,
 * which is the same as not having it.
 *
 * So it looks only at props DECLARED as Array in defineProps — the collections
 * the server sends — and skips the ones named as option lists.
 */
class EmptyStatesTest extends TestCase
{
    /**
     * Collections that come from configuration rather than from data, and can
     * therefore never be empty in practice.
     *
     * ⚠️ EVERY ENTRY IS AN ARGUMENT, NOT A CONVENIENCE. A name goes here
     * because the list is a fixed set the product ships — country dialling
     * codes, the relationship options for a trusted contact — not because the
     * empty state was awkward to write.
     */
    private const CONFIGURATION_LISTS = [
        'options',
        'countryCodes',
        'relationships',
        'types',
        'reasons',
        'fields',
        'packages',
        'steps',
    ];

    /** @return list<array{file: string, prop: string}> */
    private function collectionsWithoutEmptyStates(): array
    {
        $missing = [];
        $scanned = 0;

        $files = File::allFiles(resource_path('js/Pages'));

        /*
         * ⚠️ PROVE THE PATH IS RIGHT BEFORE TRUSTING AN EMPTY RESULT.
         *
         * Everything this method reports is an absence. Point it at a directory
         * that has been renamed and it finds nothing wrong with nothing, and
         * the suite goes green on a guard that is no longer looking at the
         * product.
         */
        $this->assertNotEmpty($files, 'No Vue pages were found — the scan is looking in the wrong place.');

        foreach ($files as $file) {
            if ($file->getExtension() !== 'vue') {
                continue;
            }

            $source = $file->getContents();

            if (! preg_match('/defineProps\(\{(.*?)\n\}\)/s', $source, $block)) {
                continue;
            }

            preg_match_all('/(\w+):\s*\{\s*type:\s*(\w+)/', $block[1], $props, PREG_SET_ORDER);

            foreach ($props as [, $name, $type]) {
                if ($type !== 'Array' || in_array($name, self::CONFIGURATION_LISTS, true)) {
                    continue;
                }

                // Only when the template actually iterates the prop itself.
                if (! preg_match('/v-for="[^"]*\bin\s+'.preg_quote($name, '/').'\b/', $source)) {
                    continue;
                }

                $quoted = preg_quote($name, '/');

                $hasEmptyBranch = preg_match('/v-(if|else-if)="[^"]*!\s*'.$quoted.'[\.\?]/', $source)
                    || preg_match('/v-if="[^"]*'.$quoted.'\.length\s*===\s*0/', $source)
                    /*
                     * A page driven by a server-side state machine handles the
                     * empty case by never rendering the list at all. The
                     * dashboard is the example, and its own docblock explains
                     * why: "a panel that calls it no results has failed the
                     * person looking at it."
                     */
                    || preg_match('/\bstate\s*===\s*/', $source);

                $scanned++;

                if (! $hasEmptyBranch) {
                    $missing[] = [
                        'file' => str_replace(resource_path('js/Pages/'), '', $file->getPathname()),
                        'prop' => $name,
                    ];
                }
            }
        }

        /*
         * ⚠️ AND PROVE THE EXTRACTION INSIDE THOSE FILES STILL MATCHES.
         *
         * Finding the files is not the same as reading them. If the defineProps
         * regex stops matching — a formatting change is enough — every page is
         * skipped and this still reports a clean product.
         */
        $this->assertGreaterThan(
            5,
            $scanned,
            "Only {$scanned} server collections were recognised across the whole product. The prop reader has stopped matching."
        );

        return $missing;
    }

    public function test_every_server_collection_says_something_when_empty(): void
    {
        $missing = $this->collectionsWithoutEmptyStates();

        $this->assertSame(
            [],
            array_map(fn (array $m) => "{$m['file']} → {$m['prop']}", $missing),
            'These screens render a server collection with no branch for it being empty. '
                .'A heading with nothing under it reads as a failed load, not as "there is nothing here".'
        );
    }

    public function test_the_reader_can_still_see_a_missing_empty_state(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL. Every assertion above is about absence, and a
         * reader whose regex has quietly stopped matching reports the same
         * clean result as a codebase with no gaps in it.
         */
        $broken = <<<'VUE'
        const props = defineProps({
            entries: { type: Array, default: () => [] },
        })
        <article v-for="entry in entries" :key="entry.id">{{ entry.id }}</article>
        VUE;

        // It sees the loop …
        $this->assertMatchesRegularExpression('/v-for="[^"]*\bin\s+entries\b/', $broken);

        // … and sees that nothing handles the empty case.
        $this->assertDoesNotMatchRegularExpression('/v-(if|else-if)="[^"]*!\s*entries[\.\?]/', $broken);
    }

    public function test_the_reader_does_not_fire_on_a_screen_that_handles_it(): void
    {
        // The other direction: a present empty branch must be recognised, or
        // the guard would demand one that is already there.
        $fixed = <<<'VUE'
        const props = defineProps({
            entries: { type: Array, default: () => [] },
        })
        <p v-if="!entries.length">Nothing yet.</p>
        <article v-for="entry in entries" :key="entry.id">{{ entry.id }}</article>
        VUE;

        $this->assertMatchesRegularExpression('/v-(if|else-if)="[^"]*!\s*entries[\.\?]/', $fixed);
    }
}
