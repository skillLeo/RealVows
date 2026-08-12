<?php

namespace App\Services\Matching\Copy;

use App\Models\Profile;
use App\Support\ProfileOptions;

/**
 * The one or two sentences a member reads under a score.
 *
 * ⚠️ EVERY COMPONENT IS NOW SAYABLE, AND THAT IS NEW.
 *
 * This class used to strip an affinity component before choosing a sentence,
 * because that component was derived from date of birth and no honest
 * sentence could describe it. The component is deleted. What is left is preferences,
 * values, lifestyle, personality and physical, all of which are things the two
 * people typed about themselves.
 *
 * So the old structural defence is gone because the thing it defended against
 * is gone. The `except('affinity')` call stayed for one commit after the
 * deletion, matching a key that no longer exists, which is a guard that has
 * quietly stopped guarding. It has been removed rather than left to reassure
 * somebody reading this in a year.
 *
 * Templates are selected deterministically from the pair's ids, so a member
 * refreshing the page sees the same sentence, and a rerun writes the same
 * copy. Variety across the platform, stability for one person.
 */
class RationaleWriter
{
    /**
     * Keyed by the safe component that scored highest, then by how well.
     * Forty-eight fragments so members do not read the same line twice.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const TEMPLATES = [
        'preferences' => [
            'high' => [
                'You are looking for very much what each other is offering.',
                'What you each hope for lines up unusually closely.',
                'Both of you described someone a lot like the other.',
                'On the practical things — age, distance, the shape of a life — you agree.',
            ],
            'mid' => [
                'Most of what you each hope for is met here.',
                'You are looking in broadly the same direction.',
                'There is real overlap in what you each said you wanted.',
                'Close on the things you each said mattered most.',
            ],
        ],
        'values' => [
            'high' => [
                'You put the same things first, and in nearly the same order.',
                'A shared sense of what a lasting relationship is actually for.',
                'Your priorities are closely aligned, right down to the ranking.',
                'The things you each said matter most are the same things.',
                'Aligned on faith, family and the pace you want to move at.',
                'A patient, home-building match with closely aligned family values.',
            ],
            'mid' => [
                'You share several of the values you each ranked highest.',
                'Common ground on what matters, with room to learn from the differences.',
                'Similar expectations about commitment and where it is heading.',
                'Enough shared ground on the important things to build from.',
            ],
        ],
        'lifestyle' => [
            'high' => [
                'Your day-to-day lives would fit together easily.',
                'The practical habits line up — the things that quietly matter every day.',
                'Similar routines, similar households, similar pace.',
                'You live in compatible ways, which is underrated and hard to find.',
            ],
            'mid' => [
                'Broadly compatible habits, with a few differences to talk through.',
                'Your lifestyles would sit alongside each other comfortably.',
                'Enough in common day to day to make things straightforward.',
                'Similar enough in how you live for the differences to be interesting.',
            ],
        ],
        'personality' => [
            'high' => [
                'You are wired in ways that tend to bring out the best in each other.',
                'A natural fit in temperament, with enough difference to stay interesting.',
                'Shared warmth and directness, with aligned expectations on commitment.',
                'You approach decisions in similar ways, which spares a lot of friction.',
                'You both value independence and a similar pace in a relationship.',
            ],
            'mid' => [
                'Complementary temperaments — different enough to balance each other.',
                'You would likely find each other easy company.',
                'Some real common ground in how you each see things.',
                'Different in useful ways, similar where it counts.',
            ],
        ],
        'physical' => [
            'high' => [
                'You each fit what the other described.',
                'A mutual match on what you both said you were drawn to.',
            ],
            'mid' => [
                'Close to what you each had in mind.',
                'Within what you both described looking for.',
            ],
        ],
    ];

    /** Opening clauses, so two sentences never start the same way twice. */
    private const OPENERS = [
        'high' => [
            'A strong match.',
            'One of the closer matches we have found for you.',
            'This one stands out.',
            'A notably good fit.',
        ],
        'mid' => [
            'A promising match.',
            'Worth your attention.',
            'A genuine fit.',
            'This one is worth a look.',
        ],
    ];

    public function write(Profile $seeker, Profile $candidate, array $result): string
    {
        $components = collect($result['components'] ?? [])
            ->sortByDesc('raw');

        if ($components->isEmpty()) {
            return 'A considered match, chosen for you rather than found by browsing.';
        }

        $seed = $seeker->id * 31 + $candidate->id * 17;

        $sentences = [];

        $topKey = $components->keys()->first();
        $topRaw = $components->first()['raw'];

        $sentences[] = $this->pick(self::OPENERS[$this->band($topRaw)], $seed);
        $sentences[] = $this->fragment($topKey, $topRaw, $seed);

        // A second component, only when it is genuinely strong. One honest
        // sentence beats two padded ones.
        $second = $components->skip(1)->take(1);

        if ($second->isNotEmpty() && $second->first()['raw'] >= 70) {
            $sentences[] = $this->fragment($second->keys()->first(), $second->first()['raw'], $seed + 7);
        }

        return implode(' ', array_filter($sentences));
    }

    private function fragment(string $component, float $raw, int $seed): ?string
    {
        $set = self::TEMPLATES[$component][$this->band($raw)] ?? null;

        return $set ? $this->pick($set, $seed) : null;
    }

    private function band(float $raw): string
    {
        return $raw >= 80 ? 'high' : 'mid';
    }

    /** Deterministic: the same pair always reads the same sentence. */
    private function pick(array $options, int $seed): string
    {
        return $options[$seed % count($options)];
    }

    /**
     * Every string this class can possibly emit. Used by the guardrail test
     * to prove the forbidden terms cannot appear in generated copy, without
     * having to generate a thousand matches to find out.
     *
     * @return list<string>
     */
    public static function everyFragment(): array
    {
        $all = [];

        foreach (self::OPENERS as $band) {
            $all = [...$all, ...$band];
        }

        foreach (self::TEMPLATES as $component) {
            foreach ($component as $band) {
                $all = [...$all, ...$band];
            }
        }

        $all[] = 'A considered match, chosen for you rather than found by browsing.';

        return $all;
    }

    /** Sanity check for the test suite: the library is as large as promised. */
    public static function fragmentCount(): int
    {
        return count(self::everyFragment());
    }

    /**
     * The vocabularies this class is allowed to draw on. Deliberately does
     * NOT include anything derived from a date of birth.
     */
    public static function safeComponents(): array
    {
        return array_keys(self::TEMPLATES);
    }

    public static function readsProfileOptions(): array
    {
        return [ProfileOptions::CORE_VALUES, ProfileOptions::MARRIAGE_TIMELINE];
    }
}
