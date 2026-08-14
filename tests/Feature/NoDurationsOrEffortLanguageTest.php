<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * ⚠️ NEVER TELL SOMEBODY HOW LONG A THING WILL TAKE BEFORE THEY START IT.
 *
 * The client, on the registration email: "using the word whole profile, using
 * the word 10 minutes. You don't want to tell people that, because people are
 * going to think, I have to wait 10 whole minutes to do my profile? Those are
 * not information you say to people. You keep the language simple."
 *
 * He is right, and the reason generalises past the one email. A duration in
 * front of a task turns it into work before it has begun. Nobody reads "ten
 * minutes" and feels reassured — they read it and decide to come back later.
 *
 * ── ⚠️ THE DISTINCTION THIS GUARD HAS TO PRESERVE ─────────────────────────
 *
 * "Step 3 of 7", shown while somebody is INSIDE the builder, is orientation. It
 * tells a person already committed how far along they are, and removing it
 * would leave them in an unmarked corridor.
 *
 * "Seven steps" or "ten minutes", shown BEFORE they begin, is a promise about
 * effort. Same numbers, opposite effect, and only the second is banned.
 *
 * So this reads copy that greets a member — the deck, the pages, the public
 * site — and deliberately does not read the progress indicator.
 */
class NoDurationsOrEffortLanguageTest extends TestCase
{
    /**
     * ⚠️ PHRASES, NOT WORDS. Banning the digit seven would take out the
     * progress indicator and every honest sentence containing a number.
     */
    private const EFFORT = [
        '10 minutes' => 'a duration',
        'ten minutes' => 'a duration',
        'a few minutes' => 'a duration',
        'takes about' => 'a duration',
        'only takes' => 'effort framing',
        'whole profile' => 'effort framing',
        'seven steps' => 'a step count promised in advance',
        '7 steps' => 'a step count promised in advance',
    ];

    /**
     * ⚠️ KNOWN, REPORTED, AND DELIBERATELY NOT YET REWRITTEN.
     *
     * Both carry the exact fault this guard exists to stop, and both are held
     * by an instruction rather than by oversight:
     *
     *  · `confirm-email` is the email the client objected to by name. He said
     *    earlier wording was better and did not say which, and is sending a
     *    screenshot. Guessing costs a revision and reads as not listening.
     *
     *  · `verification-rejected` was found by this sweep. The brief is explicit
     *    that the other twelve are to be LISTED rather than rewritten in this
     *    pass, so he can take them one at a time.
     *
     * ⚠️ LISTED HERE RATHER THAN EXCLUDED FROM THE SWEEP. An exclusion would
     * make the debt invisible and this file would go green over a fault it was
     * written to catch. Deleting an entry is the last step of fixing one.
     *
     * @var array<string, string>
     */
    private const AWAITING_REWRITE = [
        'resources/views/emails/confirm-email.blade.php' => 'Blocked on the client\'s screenshot of the wording he preferred.',
        'resources/views/emails/verification-rejected.blade.php' => 'Reported, not rewritten. The brief takes the other emails one at a time.',
    ];

    /** Surfaces a member reads. The builder component is excluded on purpose. */
    private const SURFACES = [
        'lang/en/realvows.php',
        'resources/views/public',
        'resources/views/emails',
        'resources/js/Pages/Auth',
        'resources/js/Pages/Profile/Steps',
    ];

    public function test_no_member_facing_copy_promises_effort(): void
    {
        $offences = [];
        $known = [];
        $scanned = 0;

        foreach (self::SURFACES as $surface) {
            $path = base_path($surface);

            $files = is_dir($path)
                ? array_map(fn ($f) => $f->getPathname(), File::allFiles($path))
                : [$path];

            foreach ($files as $file) {
                if (! is_file($file)) {
                    continue;
                }

                $scanned++;
                $source = strtolower(File::get($file));

                // Comments explain the rule and quote the banned phrases.
                $source = preg_replace('#/\*.*?\*/|\{\{--.*?--\}\}#s', '', $source) ?? $source;

                $relative = str_replace(base_path().'/', '', $file);

                foreach (self::EFFORT as $phrase => $why) {
                    if (! str_contains($source, $phrase)) {
                        continue;
                    }

                    if (array_key_exists($relative, self::AWAITING_REWRITE)) {
                        $known[$relative] = true;

                        continue;
                    }

                    $offences[] = "{$relative} → “{$phrase}” ({$why})";
                }
            }
        }

        /*
         * ⚠️ THE SWEEP IS PROVED TO HAVE READ SOMETHING. Every finding below is
         * an absence, and a wrong path reads nothing and reports perfectly.
         */
        $this->assertGreaterThan(10, $scanned, 'Almost no copy files were read.');

        /*
         * ⚠️ AN ENTRY THAT NO LONGER MATCHES ANYTHING IS A DEAD EXEMPTION, and
         * a dead exemption is how the NEXT file at that path inherits a pass
         * nobody granted it. Rewriting one of these means deleting its line.
         */
        $this->assertSame(
            array_keys(self::AWAITING_REWRITE),
            array_keys($known),
            'A file awaiting a rewrite no longer carries the fault it was excused for. '
                .'Remove it from AWAITING_REWRITE.',
        );

        $this->assertSame([], $offences, implode("\n  ", array_merge([
            'These tell a member how long something takes, or how much of it there is, before',
            'they have started. The client asked for this to go: it turns a small thing into',
            'work in the reader\'s mind before they have begun.',
        ], $offences)));
    }

    /**
     * ⚠️ THE PROGRESS INDICATOR SURVIVES, AND THAT IS NOT AN OVERSIGHT.
     *
     * A guard that also stripped orientation from inside the task would leave
     * somebody halfway through an unmarked form with no idea how far they had
     * come — a worse experience than the one being fixed, arrived at by
     * applying a good rule one step too far.
     */
    public function test_the_step_indicator_inside_the_builder_is_untouched(): void
    {
        $builder = File::get(base_path('resources/js/Pages/Profile/Builder.vue'));

        $this->assertStringContainsString(
            'currentStepNumber',
            $builder,
            'The builder no longer shows which step a member is on. That is orientation while '
                .'they are inside the task, not a promise made before they start.',
        );
    }

    public function test_the_reader_catches_a_duration_and_leaves_honest_copy_alone(): void
    {
        /*
         * ⚠️ POSITIVE CONTROL. The assertion above is an empty-array check over
         * a phrase list, and a list that had drifted out of the copy entirely
         * reports exactly the same clean result as copy with nothing wrong.
         */
        $bad = strtolower('Building your whole profile only takes about 10 minutes.');

        $caught = array_keys(array_filter(
            self::EFFORT,
            fn ($why, $phrase) => str_contains($bad, $phrase),
            ARRAY_FILTER_USE_BOTH,
        ));

        $this->assertContains('10 minutes', $caught);
        $this->assertContains('whole profile', $caught);
        $this->assertContains('only takes', $caught);

        // And ordinary copy must pass untouched, including a sentence with a
        // number in it that is not a promise about effort.
        $good = strtolower('Joining is free, and the first 100 members keep every introduction '
            .'they receive at no cost.');

        $this->assertSame([], array_keys(array_filter(
            self::EFFORT,
            fn ($why, $phrase) => str_contains($good, $phrase),
            ARRAY_FILTER_USE_BOTH,
        )));
    }
}
