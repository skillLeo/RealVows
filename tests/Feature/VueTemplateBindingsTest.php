<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ A TEMPLATE THAT CALLS AN UNDEFINED HELPER RENDERS NOTHING, SILENTLY.
 *
 * The admin Settings screen lost every field on the live site. The cause was
 * not a logic error — it was an edit that did not apply. A search-and-replace
 * targeted `const optionList`, the function was actually called `asOptions`,
 * the replacement matched nothing, and the file was written back unchanged
 * except for the template, which now called `isRelevant(field)`.
 *
 * Vue does not fail loudly for that. `isRelevant` was undefined, every field
 * evaluated falsy, and the screen rendered an empty card. The build succeeded.
 * Every test passed. The client opened Settings and saw nothing.
 *
 * ⚠️ THE SAME FAILURE CLASS AS EVERY OTHER ONE IN THIS PROJECT: something
 * reported success while doing nothing. This time it was the editing tool
 * rather than a guard, which is why no existing test caught it.
 *
 * This checks every helper a template calls actually exists in its script.
 */
class VueTemplateBindingsTest extends TestCase
{
    /**
     * Names that are always available in a template and are never declared in
     * the script block.
     */
    private const PROVIDED = [
        // Vue and Inertia built-ins.
        '$t', '$page', '$slots', '$attrs', '$emit', '$props', '$refs', '$el',
        // Common JS globals used inline.
        'Object', 'Array', 'String', 'Number', 'Boolean', 'Math', 'JSON', 'Date',
        'parseInt', 'parseFloat', 'isNaN', 'console', 'route', 'window',
    ];

    /**
     * ⚠️ EVERY `name(` IN A TEMPLATE MUST RESOLVE TO SOMETHING THE SCRIPT
     * DECLARES, OR TO A PROP.
     */
    public function test_every_helper_a_template_calls_is_defined(): void
    {
        $offences = [];

        foreach (ScannedFiles::withExtension(File::allFiles(resource_path('js')), ['vue'], 'components', 20) as $path) {
            $source = File::get($path);
            $relative = str_replace(base_path().'/', '', $path);

            $template = $this->sectionBetween($source, '<template>', '</template>');
            $script = $this->sectionBetween($source, '<script setup>', '</script>');

            if ($template === '' || $script === '') {
                continue;
            }

            /*
             * Calls that look like a helper: an identifier immediately
             * followed by `(`. Deliberately not method calls (`a.b(`), which
             * belong to whatever object they hang off.
             */
            preg_match_all('/(?<![\w.$])([a-zA-Z_$][\w$]*)\s*\(/', $template, $matches);

            foreach (array_unique($matches[1]) as $name) {
                if (in_array($name, self::PROVIDED, true)) {
                    continue;
                }

                // Declared as a function, const, let, prop, or import.
                $declared = preg_match(
                    '/(function\s+'.preg_quote($name, '/').'\b'
                    .'|(?:const|let|var)\s+'.preg_quote($name, '/').'\b'
                    .'|\b'.preg_quote($name, '/').'\s*:\s*\{'          // a prop
                    .'|import\s+[^;]*\b'.preg_quote($name, '/').'\b)/',
                    $script,
                ) === 1;

                if (! $declared) {
                    $offences[] = "{$relative}: template calls {$name}() but the script never declares it";
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "A template calls something that does not exist. Vue renders nothing and says "
                ."nothing:\n  ".implode("\n  ", $offences)
        );
    }

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). The scan is a negative assertion over
     * source and is worth nothing unless it can be shown catching the exact
     * bug that prompted it, and NOT flagging a correct file.
     */
    public function test_the_scan_can_see_an_undefined_helper(): void
    {
        $broken = "<script setup>\nconst asOptions = () => {};\n</script>\n"
            ."<template><div v-if=\"isRelevant(field)\">x</div></template>";

        $fixed = "<script setup>\nconst isRelevant = () => true;\n</script>\n"
            ."<template><div v-if=\"isRelevant(field)\">x</div></template>";

        $this->assertTrue($this->hasUndefinedCall($broken), 'The scan cannot see the bug it exists for.');
        $this->assertFalse($this->hasUndefinedCall($fixed), 'The scan flags a correctly defined helper.');
    }

    private function hasUndefinedCall(string $source): bool
    {
        $template = $this->sectionBetween($source, '<template>', '</template>');
        $script = $this->sectionBetween($source, '<script setup>', '</script>');

        preg_match_all('/(?<![\w.$])([a-zA-Z_$][\w$]*)\s*\(/', $template, $matches);

        foreach (array_unique($matches[1]) as $name) {
            if (in_array($name, self::PROVIDED, true)) {
                continue;
            }

            if (preg_match('/(?:const|let|var|function)\s+'.preg_quote($name, '/').'\b/', $script) !== 1) {
                return true;
            }
        }

        return false;
    }

    private function sectionBetween(string $source, string $open, string $close): string
    {
        $start = strpos($source, $open);

        if ($start === false) {
            return '';
        }

        $end = strrpos($source, $close);

        if ($end === false || $end <= $start) {
            return '';
        }

        return substr($source, $start + strlen($open), $end - $start - strlen($open));
    }
}
