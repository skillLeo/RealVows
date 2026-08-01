<?php

namespace App\Support;

/**
 * Every option list in the profile builder, defined once.
 *
 * The server validates against these and the browser renders from them, so
 * a list can never drift between the two. Anything with four or fewer
 * options is rendered as a segmented control, never a dropdown — that is a
 * locked rule of the design system, and the counts here are chosen with it
 * in mind.
 */
class ProfileOptions
{
    /** The seven steps, in the agreed order, with the agreed names. */
    public const STEPS = ['Account', 'Personal', 'Physical', 'Photos', 'Lifestyle', 'Values', 'Preferences'];

    public const GENDER = [
        'man' => 'Man',
        'woman' => 'Woman',
        'non_binary' => 'Non-binary',
    ];

    public const BODY_TYPE = [
        'slim' => 'Slim or petite',
        'athletic' => 'Athletic or muscular',
        'average' => 'Average',
        'curvy' => 'Curvy',
        'plus_size' => 'Plus-size',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    public const FAITH_IMPORTANCE = [
        'not_important' => 'Not important',
        'somewhat' => 'Somewhat',
        'important' => 'Important',
        'essential' => 'Essential',
    ];

    public const FREQUENCY = [
        'never' => 'Never',
        'socially' => 'Socially',
        'regularly' => 'Regularly',
    ];

    public const HAS_CHILDREN = [
        'none' => 'None',
        'yes_at_home' => 'Yes, at home',
        'yes_independent' => 'Yes, grown up',
    ];

    public const WANTS_CHILDREN = [
        'yes' => 'Yes',
        'no' => 'No',
        'open' => 'Open to it',
        'undecided' => 'Undecided',
    ];

    public const MARRIAGE_TIMELINE = [
        'within_year' => 'Within a year',
        'one_to_two' => 'One to two years',
        'two_plus' => 'Two years or more',
        'no_fixed' => 'No fixed timeline',
    ];

    public const CONFLICT_STYLE = [
        'discuss_now' => 'Talk it through straight away',
        'need_space' => 'Take some space first',
        'seek_compromise' => 'Look for the middle ground',
        'avoid' => 'Let it settle on its own',
    ];

    /** Members rank their top five, in order. */
    public const CORE_VALUES = [
        'faith' => 'Faith',
        'family' => 'Family',
        'honesty' => 'Honesty',
        'kindness' => 'Kindness',
        'loyalty' => 'Loyalty',
        'ambition' => 'Ambition',
        'independence' => 'Independence',
        'generosity' => 'Generosity',
        'humour' => 'Humour',
        'stability' => 'Stability',
        'adventure' => 'Adventure',
        'education' => 'Education',
        'health' => 'Health',
        'community' => 'Community',
        'tradition' => 'Tradition',
    ];

    public const LOVE_LANGUAGES = [
        'words' => 'Words of affirmation',
        'time' => 'Quality time',
        'acts' => 'Acts of service',
        'gifts' => 'Gifts',
        'touch' => 'Physical touch',
    ];

    /**
     * Four scales, each stored 0–100. Rendered as sliders with a brass thumb
     * on a hairline track.
     */
    public const PERSONALITY_SCALES = [
        'social' => ['left' => 'Introvert', 'right' => 'Extrovert'],
        'planning' => ['left' => 'Planner', 'right' => 'Spontaneous'],
        'decisions' => ['left' => 'Emotional', 'right' => 'Logical'],
        'outlook' => ['left' => 'Traditional', 'right' => 'Progressive'],
    ];

    public const RELATIONSHIP_GOAL = [
        'marriage_soon' => 'Marriage, as soon as I meet the right person',
        'marriage_eventually' => 'Marriage, without rushing it',
        'serious_first' => 'A serious relationship leading to marriage',
    ];

    public const DISTANCE_PREFERENCE = [
        'same_city' => 'My city',
        'same_country' => 'My country',
        'anywhere' => 'Anywhere',
    ];

    public const DEAL_BREAKERS = [
        'smoking' => 'Smoking',
        'drinking' => 'Drinking',
        'has_children' => 'Already has children',
        'no_children_wanted' => 'Does not want children',
        'different_faith' => 'A different faith',
        'long_distance' => 'Long distance',
    ];

    public const PETS = [
        'none' => 'None',
        'dog' => 'Dog',
        'cat' => 'Cat',
        'other' => 'Other',
    ];

    /**
     * Prompts shown in the bio field. Guidance, not a template — the point
     * is to help someone start, not to write it for them.
     */
    public const BIO_PROMPTS = [
        'What does an ordinary, good week look like for you?',
        'What are you working towards in the next few years?',
        'What would your closest friend say you are like?',
        'What matters to you in a home?',
    ];

    public const BIO_MIN_WORDS = 150;
    public const BIO_MAX_WORDS = 300;

    /**
     * Shape used for a segmented control or a select in the browser.
     */
    public static function forSelect(array $map): array
    {
        return collect($map)
            ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
            ->values()
            ->all();
    }

    public static function allForBrowser(): array
    {
        return [
            'gender' => self::forSelect(self::GENDER),
            'bodyType' => self::forSelect(self::BODY_TYPE),
            'faithImportance' => self::forSelect(self::FAITH_IMPORTANCE),
            'frequency' => self::forSelect(self::FREQUENCY),
            'hasChildren' => self::forSelect(self::HAS_CHILDREN),
            'wantsChildren' => self::forSelect(self::WANTS_CHILDREN),
            'marriageTimeline' => self::forSelect(self::MARRIAGE_TIMELINE),
            'conflictStyle' => self::forSelect(self::CONFLICT_STYLE),
            'coreValues' => self::forSelect(self::CORE_VALUES),
            'loveLanguages' => self::forSelect(self::LOVE_LANGUAGES),
            'personalityScales' => self::PERSONALITY_SCALES,
            'relationshipGoal' => self::forSelect(self::RELATIONSHIP_GOAL),
            'distancePreference' => self::forSelect(self::DISTANCE_PREFERENCE),
            'dealBreakers' => self::forSelect(self::DEAL_BREAKERS),
            'pets' => self::forSelect(self::PETS),
            'bioPrompts' => self::BIO_PROMPTS,
            'bioMinWords' => self::BIO_MIN_WORDS,
            'bioMaxWords' => self::BIO_MAX_WORDS,
        ];
    }
}
