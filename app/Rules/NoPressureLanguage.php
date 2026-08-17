<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * ⚠️ NOTHING A MEMBER READS MAY MANUFACTURE URGENCY OR SCARCITY.
 *
 * This is the register that turns a matchmaking product into a dating product,
 * and it does not arrive as a decision. It arrives one small edit at a time,
 * from somebody thinking about conversion, into a text box that felt harmless
 * because it was only a label.
 *
 * ── ⚠️ WHY A VALIDATOR AND NOT A CODE REVIEW ──────────────────────────────
 *
 * Every other rule of this kind is enforced against the repository, and a test
 * reading `resources/` cannot see a settings row. An admin can type "Only 3
 * left!" into a package badge and it reaches every member on the pricing page
 * without touching a single file, without a commit, and without CI ever
 * running. That is a hole in the anti-drift guarantee, not a hypothetical.
 *
 * ── ⚠️ WHAT IT DELIBERATELY DOES NOT CATCH ────────────────────────────────
 *
 * A rule that also refused honest sentences would be worse than no rule,
 * because the person it blocks is a colleague trying to write something true
 * and the thing they will do next is find a way around it.
 *
 * So a factual expiry is allowed. "Your reset link expires in one hour" states
 * a security property. "Ending soon!" states nothing and exists to hurry
 * somebody. The first is about a token, the second is about the reader.
 */
class NoPressureLanguage implements ValidationRule
{
    /**
     * ⚠️ PHRASES AND SHAPES, NOT SINGLE WORDS.
     *
     * Banning "only" or "last" would refuse "the only person you need to tell"
     * and "your last name", which are ordinary English. Each entry here is
     * either a phrase that has no honest use in this product, or a numeric
     * shape that only appears when somebody is counting down at a reader.
     *
     * @var array<string, string>
     */
    private const PATTERNS = [
        // Countdowns and deadlines aimed at the reader.
        '/\bhurry\b/i' => 'hurries the reader',
        '/\bact (fast|now|quickly)\b/i' => 'hurries the reader',
        '/\blast chance\b/i' => 'manufactures a deadline',
        '/\bending soon\b/i' => 'manufactures a deadline',
        '/\bends (soon|today|tonight)\b/i' => 'manufactures a deadline',
        '/\bfinal (call|chance|hours?|days?)\b/i' => 'manufactures a deadline',
        '/\b(don\'?t|do not) miss\b/i' => 'manufactures a deadline',
        '/\bwhile stocks last\b/i' => 'manufactures scarcity',
        '/\blimited time\b/i' => 'manufactures a deadline',
        '/\btime is running out\b/i' => 'manufactures a deadline',
        '/\brunning out\b/i' => 'manufactures scarcity',
        '/\bbefore it(\'s| is) (gone|too late)\b/i' => 'manufactures a deadline',

        /*
         * ⚠️ STOCK COUNTS. A number in front of "left", "remaining" or
         * "spaces" is the shape, whether written as a digit or a word, because
         * this product has no stock and never will. An introduction is not
         * inventory.
         */
        '/\bonly\s+(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\b/i' => 'counts down at the reader',
        '/\b(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s+(left|remaining|spaces?|spots?|places?)\b/i' => 'counts stock',
        '/\bselling (fast|out)\b/i' => 'manufactures scarcity',
        '/\balmost (gone|full|sold out)\b/i' => 'manufactures scarcity',
        '/\bsold out\b/i' => 'manufactures scarcity',
        '/\bspaces? (are )?(limited|filling)\b/i' => 'manufactures scarcity',
        '/\bfilling up\b/i' => 'manufactures scarcity',

        // Presence and activity signals. This product has no feed to be live on.
        '/\bonline now\b/i' => 'implies live presence',
        '/\bactive now\b/i' => 'implies live presence',
        '/\bjust viewed\b/i' => 'implies surveillance of a member',
        '/\bviewed your\b/i' => 'implies surveillance of a member',
        '/\bpeople are (looking|viewing|watching)\b/i' => 'implies a crowd',

        // Gamification.
        '/\bstreak\b/i' => 'gamifies',
        '/\blevel up\b/i' => 'gamifies',
        '/\bearn points\b/i' => 'gamifies',
        '/\bleaderboard\b/i' => 'gamifies',
    ];

    public function __construct(private readonly string $field = 'This') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        foreach (self::PATTERNS as $pattern => $why) {
            if (preg_match($pattern, $value)) {
                $fail(
                    "{$this->field} {$why}, and members read it. RealVows does not manufacture "
                    ."urgency or scarcity: there is no stock, nothing is running out, and nobody "
                    ."is being timed. Say what is true and leave it there."
                );

                return;
            }
        }
    }

    /**
     * ⚠️ EXPOSED SO A TEST CAN PROVE THE READER STILL READS.
     *
     * Every assertion built on this rule is "the save was refused" or "the save
     * went through", and both are equally satisfied by a rule that matches
     * nothing at all.
     *
     * @return array<int, string>
     */
    public static function patterns(): array
    {
        return array_keys(self::PATTERNS);
    }
}
