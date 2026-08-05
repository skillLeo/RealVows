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
        // Language, not calls. `if (` and `return (` are not helpers.
        'if', 'else', 'return', 'typeof', 'instanceof', 'new', 'in', 'of',
        'for', 'while', 'switch', 'catch', 'function', 'await', 'delete', 'void',
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

            foreach ($this->calledNames($template) as $name) {
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

        /*
         * ⚠️ THE OVER-EAGER HALF. These are the three real false positives the
         * first version of this scan produced, kept as fixtures so a future
         * widening of the pattern fails here rather than in somebody's inbox.
         */
        $noise = "<script setup>\nconst x = 1;\n</script>\n"
            .'<template>'
            .'<span style="color: var(--rose)">a</span>'
            .'<label>Anything to add (optional)</label>'
            .'<input label="Shortest height (cm)">'
            .'</template>';

        $this->assertFalse(
            $this->hasUndefinedCall($noise),
            'The scan flags CSS functions and ordinary prose. An over-eager reader is as useless '
                .'as a blind one — it produces noise that trains people to ignore the guard.'
        );
    }

    private function hasUndefinedCall(string $source): bool
    {
        $template = $this->sectionBetween($source, '<template>', '</template>');
        $script = $this->sectionBetween($source, '<script setup>', '</script>');

        foreach ($this->calledNames($template) as $name) {
            if (preg_match('/(?:const|let|var|function)\s+'.preg_quote($name, '/').'\b/', $script) !== 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * ⚠️ ONLY WHAT VUE ACTUALLY EVALUATES.
     *
     * The first version of this scan ran the identifier-call pattern over the
     * whole template and produced three false positives on the first run:
     *
     *   · `style="color: var(--rose)"`      — a CSS function in a STATIC
     *                                          attribute. Vue never evaluates
     *                                          it; it is a string.
     *   · `label="Shortest height (cm)"`     — English prose. The word "height"
     *                                          happens to be followed by a
     *                                          bracket.
     *   · `>Anything to add (optional)<`     — the same thing, in body text.
     *
     * ⚠️ AN OVER-EAGER READER IS AS DEAD AS A BLIND ONE, and worse in
     * practice: a blind guard fails silently, whereas a noisy one trains people
     * to skim its output and eventually to delete it. Three false positives on
     * a first run is how a guard earns a reputation it never recovers from.
     *
     * So the scan reads expressions rather than markup: the inside of `{{ }}`,
     * and the values of bound attributes — `:prop`, `v-if`, `@click`. A static
     * `style` or `label` attribute is not evaluated by Vue and cannot contain
     * an undefined helper, by definition.
     *
     * @return list<string>
     */
    private function calledNames(string $template): array
    {
        $expressions = [];

        // Interpolations: {{ anything }}
        preg_match_all('/\{\{(.+?)\}\}/s', $template, $curly);
        $expressions = array_merge($expressions, $curly[1]);

        /*
         * Bound attributes only. The leading `:`, `v-` or `@` is what marks an
         * attribute value as an expression rather than a string — that single
         * character is the whole difference between `style="var(--rose)"` and
         * `:style="tint(row)"`.
         */
        preg_match_all(
            '/(?:^|\s)(?::|v-|@)[A-Za-z@:.\[\]_-][\w@:.\[\]_-]*\s*=\s*"([^"]*)"/s',
            $template,
            $doubleQuoted,
        );
        $expressions = array_merge($expressions, $doubleQuoted[1]);

        preg_match_all(
            "/(?:^|\s)(?::|v-|@)[A-Za-z@:.\[\]_-][\w@:.\[\]_-]*\s*=\s*'([^']*)'/s",
            $template,
            $singleQuoted,
        );
        $expressions = array_merge($expressions, $singleQuoted[1]);

        $names = [];

        foreach ($expressions as $expression) {
            /*
             * An identifier immediately followed by `(`. Deliberately NOT
             * method calls (`a.b(`), which belong to whatever object they hang
             * off, and no `\s*` before the bracket — inside an expression a
             * space before the paren is grouping, not a call.
             */
            preg_match_all('/(?<![\w.$])([a-zA-Z_$][\w$]*)\(/', $expression, $matches);

            foreach ($matches[1] as $name) {
                if (! in_array($name, self::PROVIDED, true)) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
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
