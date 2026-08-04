<?php

namespace Tests\Feature;

use App\Mail\IntroductionDelivered;
use App\Mail\IntroductionsUnlocked;
use App\Mail\MutualInterest;
use App\Mail\SomeoneInterested;
use App\Mail\TeaserExpiring;
use App\Models\MatchRecord;
use App\Models\Profile;
use App\Models\User;
use App\Support\MatchPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\ScannedFiles;
use Tests\TestCase;

/**
 * ⚠️ A MEMBER WITH NO SURNAME IS THE COMMON CASE, NOT AN EDGE CASE.
 *
 * The privacy guard was narrowed in Phase 3: the last name is withheld until
 * both people have independently expressed interest. So on every surface
 * before mutual interest — which is nearly all of them, nearly all of the time
 * — there is a first name and nothing else.
 *
 * That makes `"$first $last"` a permanent fault in the MAIN PATH rather than a
 * rare one: a trailing space, a dangling comma after an empty name, or a bare
 * "undefined" rendered next to somebody's first name. Faults in the main path
 * do not get noticed by testing, they get noticed by members.
 *
 * ⚠️ ASSERTED ON RENDERED OUTPUT. A template can look correct and still emit a
 * dangling separator, because the emptiness arrives through a variable.
 */
class NameRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SettingsSeeder::class);
    }

    /** ⚠️ No surname, which the presenter now withholds by default. */
    private function memberWithoutSurname(): User
    {
        $user = User::factory()->create(['email' => 'kemar@example.com']);

        Profile::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Kemar',
            'last_name' => null,
        ]);

        return $user->fresh();
    }

    /** The nastier case: a name field that exists but holds only whitespace. */
    private function memberWithBlankName(): User
    {
        $user = User::factory()->create(['email' => 'blank@example.com']);

        Profile::factory()->create([
            'user_id' => $user->id,
            'first_name' => '   ',
            'last_name' => '',
        ]);

        return $user->fresh();
    }

    /** @return array<string, \App\Mail\MemberMailable> */
    private function mailablesFor(User $user): array
    {
        return [
            'introduction-delivered' => new IntroductionDelivered($user, count: 1, score: 74, rationale: 'You both put honesty first.'),
            'introductions-unlocked' => new IntroductionsUnlocked($user, count: 2),
            'mutual-interest' => new MutualInterest($user, otherFirstName: 'Omar'),
            'someone-interested' => new SomeoneInterested($user),
            'teaser-expiring' => new TeaserExpiring($user, count: 1, daysLeft: 7),
        ];
    }

    /**
     * ⚠️ THE FAULTS THIS LOOKS FOR, AND WHY EACH ONE MATTERS.
     *
     * Every one of these is what a member actually sees when a name field is
     * empty and the template assumed it would not be.
     *
     * @return array<string, string>
     */
    private function faults(): array
    {
        return [
            /*
             * "Hello ," — an empty name inside a greeting.
             *
             * ⚠️ `\s+` AND NOT `\s*`. "Hello," with no space is the CORRECT
             * fallback when there is no name at all; the fault is the gap left
             * behind by a name that rendered as nothing.
             */
            '/Hello\s+,/' => 'a greeting with a gap where the name should be',
            // "Hello  Kemar" — a collapsed field leaving a doubled space.
            '/Hello {2,}/' => 'a doubled space in a greeting',
            // JavaScript leaking an absent field into prose.
            '/\bundefined\b/i' => 'the string "undefined"',
            '/\bnull\b/' => 'the string "null"',
            /*
             * ⚠️ A SEPARATOR WITH NOTHING BEFORE IT — note the required
             * whitespace.
             *
             * The first version of this reader was `/[,;]\s*$/m`, which fires
             * on "Hello Kemar," — a perfectly correct greeting — and on every
             * stray semicolon left behind by strip_tags. It reported ten
             * offences against templates that were fine.
             *
             * An over-eager reader is exactly as useless as a blind one, and
             * worse in practice: it produces noise that trains people to
             * ignore the guard. The fix belonged in the reader, not the
             * templates.
             */
            '/\s[,;]\s*$/m' => 'a line ending in a separator with nothing before it',
        ];
    }

    // ── Emails ───────────────────────────────────────────────────────────────

    public function test_no_email_renders_a_dangling_name_for_a_member_without_a_surname(): void
    {
        $this->assertNoFaultsAcross($this->memberWithoutSurname(), 'no surname');
    }

    /** ⚠️ And a name that is present but blank, which a ternary treats as truthy. */
    public function test_no_email_renders_a_dangling_name_for_a_blank_name_field(): void
    {
        $this->assertNoFaultsAcross($this->memberWithBlankName(), 'a whitespace-only first name');
    }

    private function assertNoFaultsAcross(User $user, string $situation): void
    {
        $offences = [];

        foreach ($this->mailablesFor($user) as $key => $mailable) {
            $surfaces = [
                'html' => strip_tags($mailable->render()),
                'text' => view($mailable->content()->text, $mailable->content()->with)->render(),
            ];

            foreach ($surfaces as $part => $rendered) {
                foreach ($this->faults() as $pattern => $description) {
                    if (preg_match($pattern, $rendered, $match)) {
                        $offences[] = sprintf(
                            '%s (%s) → %s [%s]',
                            $key, $part, $description, trim($match[0]),
                        );
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "With {$situation}, these surfaces render a broken name:\n  ".implode("\n  ", $offences)
        );
    }

    // ── The match payload ────────────────────────────────────────────────────

    /**
     * ⚠️ THE PAYLOAD MUST NOT CARRY AN EMPTY NAME KEY EITHER.
     *
     * `lastName` is ABSENT before mutual interest rather than null — which is
     * also what stops a component concatenating it into a trailing space.
     */
    public function test_a_pre_mutual_payload_carries_no_empty_name_field(): void
    {
        $me = $this->memberWithoutSurname();
        $them = User::factory()->create();
        Profile::factory()->create(['user_id' => $them->id, 'first_name' => 'Idris', 'last_name' => 'Bakare']);

        $match = MatchRecord::create([
            'seeker_profile_id' => $me->profile->id,
            'matched_profile_id' => $them->profile->id,
            'total_score' => 80,
            'score_breakdown' => ['components' => []],
            'compatibility_rationale' => 'A steady match.',
            'commonalities' => [],
            'status' => MatchRecord::STATUS_UNLOCKED,
            'unlocked_at' => now(),
        ]);

        $payload = app(MatchPresenter::class)->full($match);

        $this->assertArrayNotHasKey('lastName', $payload);

        $encoded = json_encode($payload);

        $this->assertStringNotContainsString('"lastName":null', $encoded);
        $this->assertStringNotContainsString('"lastName":""', $encoded);
    }

    // ── Components ───────────────────────────────────────────────────────────

    /**
     * ⚠️ NO COMPONENT MAY CONCATENATE TWO NAME FIELDS WITHOUT FILTERING.
     *
     * `${first} ${last}` renders "Idris undefined" the moment `lastName` is
     * absent, which it now is on every pre-mutual surface. Both existing sites
     * guard correctly — this is what keeps the next one from not doing so.
     */
    public function test_no_component_concatenates_names_without_a_guard(): void
    {
        $offences = [];

        foreach (ScannedFiles::withExtension(File::allFiles(resource_path('js')), ['vue', 'js'], 'components', 20) as $path) {
            $source = File::get($path);
            $relative = str_replace(base_path().'/', '', $path);

            // An unguarded template literal joining the two name fields.
            if (preg_match('/`\$\{[^}]*firstName\}\s+\$\{[^}]*lastName\}`/', $source, $match)) {
                $guarded = str_contains($source, 'lastName')
                    && (str_contains($source, 'filter(Boolean)') || preg_match('/&&\s*[a-z.]*lastName/i', $source));

                if (! $guarded) {
                    $offences[] = "{$relative}: {$match[0]}";
                }
            }
        }

        $this->assertSame(
            [],
            $offences,
            "These components join a first and last name with no guard, so an absent surname "
                ."renders as \"undefined\" beside a real person's name:\n  ".implode("\n  ", $offences)
                ."\n\nUse [first, last].filter(Boolean).join(' ')."
        );
    }

    // ── Positive controls ────────────────────────────────────────────────────

    /**
     * ⚠️ POSITIVE CONTROL (Rule 5). Every assertion above is negative — "no
     * surface renders a fault" — which a reader that matches nothing satisfies
     * perfectly on a completely broken codebase.
     */
    public function test_the_fault_reader_can_see_every_fault_it_looks_for(): void
    {
        $samples = [
            '/Hello\s+,/' => 'Hello , welcome back',
            '/Hello {2,}/' => 'Hello  Kemar,',
            '/\bundefined\b/i' => 'Idris undefined is waiting',
            '/\bnull\b/' => 'Idris null is waiting',
            '/\s[,;]\s*$/m' => "Hello ,\n",
        ];

        foreach ($samples as $pattern => $planted) {
            $this->assertMatchesRegularExpression(
                $pattern,
                $planted,
                "The reader [{$pattern}] cannot see the fault it exists to catch."
            );
        }

        // And none of them fires on a correctly rendered greeting.
        $clean = 'Hello Kemar, someone new is waiting for you on RealVows.';

        foreach (array_keys($samples) as $pattern) {
            $this->assertDoesNotMatchRegularExpression(
                $pattern,
                $clean,
                "The reader [{$pattern}] flags a correct greeting, which would make it unusable."
            );
        }
    }

    /** ⚠️ POSITIVE CONTROL for the component scan. */
    public function test_the_component_scan_can_see_an_unguarded_concatenation(): void
    {
        $bad = 'const name = `${props.match.firstName} ${props.match.lastName}`;';
        $good = "const name = [props.match.firstName, props.match.lastName].filter(Boolean).join(' ');";

        $reader = fn (string $s) => preg_match('/`\$\{[^}]*firstName\}\s+\$\{[^}]*lastName\}`/', $s) === 1;

        $this->assertTrue($reader($bad), 'The scan cannot see an unguarded concatenation.');
        $this->assertFalse($reader($good), 'The scan flags a correctly filtered join.');
    }
}
