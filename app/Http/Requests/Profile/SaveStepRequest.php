<?php

namespace App\Http\Requests\Profile;

use App\Support\ProfileOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->profile !== null;
    }

    /**
     * Rules are deliberately permissive about EMPTINESS and strict about
     * CORRECTNESS.
     *
     * This endpoint is hit by autosave every 800ms while somebody is still
     * typing, so a half-finished step is a normal state, not an error —
     * rejecting it would throw away their work. What is never permitted is a
     * value that is present and wrong: an unknown enum, a malformed date, a
     * height of 900cm.
     *
     * Completeness is judged separately, when the member submits for
     * verification (see SubmitProfileForVerification).
     */
    public function rules(): array
    {
        return match ($this->stepKey()) {
            'personal' => $this->personalRules(),
            'physical' => $this->physicalRules(),
            'lifestyle' => $this->lifestyleRules(),
            'values' => $this->valuesRules(),
            'preferences' => $this->preferencesRules(),
            default => [],
        };
    }

    public function stepKey(): string
    {
        return strtolower((string) $this->route('step'));
    }

    private function personalRules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['nullable', 'string', 'max:60'],

            /*
             * ⚠️ THE 18+ GATE.
             *
             * The signup checkbox is a declaration; this is the enforcement.
             * `before_or_equal` against a date eighteen years ago is checked
             * on the server, so it holds regardless of what the browser sent
             * or whether JavaScript ran at all.
             */
            'date_of_birth' => [
                'nullable',
                'date',
                'before_or_equal:'.now()->subYears(18)->toDateString(),
                'after:'.now()->subYears(120)->toDateString(),
            ],

            'gender' => ['nullable', Rule::in(array_keys(ProfileOptions::GENDER))],
            'seeking' => ['nullable', Rule::in(array_keys(ProfileOptions::GENDER))],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'willing_to_relocate' => ['nullable', 'boolean'],
            'nationality' => ['nullable', 'string', 'max:120'],
        ];
    }

    private function physicalRules(): array
    {
        return [
            'height_cm' => ['nullable', 'integer', 'min:120', 'max:230'],
            'body_type' => ['nullable', Rule::in(array_keys(ProfileOptions::BODY_TYPE))],
            'eye_colour' => ['nullable', 'string', 'max:32'],
            'hair_colour' => ['nullable', 'string', 'max:32'],
            'has_tattoos' => ['nullable', 'boolean'],
            'has_piercings' => ['nullable', 'boolean'],
        ];
    }

    private function lifestyleRules(): array
    {
        return [
            'education' => ['nullable', 'string', 'max:120'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'income_range' => ['nullable', 'string', 'max:60'],
            'religion' => ['nullable', 'string', 'max:60'],
            'faith_importance' => ['nullable', Rule::in(array_keys(ProfileOptions::FAITH_IMPORTANCE))],
            'smoking' => ['nullable', Rule::in(array_keys(ProfileOptions::FREQUENCY))],
            'drinking' => ['nullable', Rule::in(array_keys(ProfileOptions::FREQUENCY))],
            'has_children' => ['nullable', Rule::in(array_keys(ProfileOptions::HAS_CHILDREN))],
            'wants_children' => ['nullable', Rule::in(array_keys(ProfileOptions::WANTS_CHILDREN))],
            'pets' => ['nullable', 'array', 'max:4'],
            'pets.*' => [Rule::in(array_keys(ProfileOptions::PETS))],
            'languages' => ['nullable', 'array', 'max:10'],
            'languages.*' => ['string', 'max:40'],
        ];
    }

    private function valuesRules(): array
    {
        return [
            'relationship_goal' => ['nullable', Rule::in(array_keys(ProfileOptions::RELATIONSHIP_GOAL))],
            'marriage_timeline' => ['nullable', Rule::in(array_keys(ProfileOptions::MARRIAGE_TIMELINE))],

            // Ordered, and the order carries meaning — first is ranked highest.
            'core_values' => ['nullable', 'array', 'max:5'],
            'core_values.*' => ['distinct', Rule::in(array_keys(ProfileOptions::CORE_VALUES))],

            'conflict_style' => ['nullable', Rule::in(array_keys(ProfileOptions::CONFLICT_STYLE))],
            'love_languages' => ['nullable', 'array', 'max:3'],
            'love_languages.*' => ['distinct', Rule::in(array_keys(ProfileOptions::LOVE_LANGUAGES))],

            'bio' => ['nullable', 'string', 'max:4000'],

            /*
             * ⚠️ WHAT THE MEMBER TYPED, WHEN THEY ACCEPTED A REWRITE.
             *
             * Sent by the profile builder only when the AI suggestion was
             * accepted, and never shown to anybody. Its only purpose is that if
             * a member is later accused of misrepresenting themselves, the
             * question "what did THEY say" has an answer — otherwise an
             * accepted rewrite is indistinguishable from their own words and
             * the platform cannot tell whether a false claim came from the
             * member or from a model it ran on their behalf.
             */
            'bio_original' => ['nullable', 'string', 'max:4000'],
            'interests' => ['nullable', 'array', 'max:12'],
            'interests.*' => ['string', 'max:40'],

            'personality_scales' => ['nullable', 'array'],
            'personality_scales.*' => ['integer', 'min:0', 'max:100'],
        ];
    }

    private function preferencesRules(): array
    {
        return [
            'age_min' => ['nullable', 'integer', 'min:18', 'max:99'],
            'age_max' => ['nullable', 'integer', 'min:18', 'max:99', 'gte:age_min'],
            'height_min_cm' => ['nullable', 'integer', 'min:120', 'max:230'],
            'height_max_cm' => ['nullable', 'integer', 'min:120', 'max:230', 'gte:height_min_cm'],
            'body_types' => ['nullable', 'array'],
            'body_types.*' => ['distinct', Rule::in(array_keys(ProfileOptions::BODY_TYPE))],
            'distance_preference' => ['nullable', Rule::in(array_keys(ProfileOptions::DISTANCE_PREFERENCE))],
            'deal_breakers' => ['nullable', 'array'],
            'deal_breakers.*' => ['distinct', Rule::in(array_keys(ProfileOptions::DEAL_BREAKERS))],
            'preferred_qualities' => ['nullable', 'array', 'max:5'],
            'preferred_qualities.*' => ['string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before_or_equal' => 'RealVows is for members aged 18 and over.',
            'date_of_birth.after' => 'Please check that date of birth.',
            'age_max.gte' => 'The oldest age needs to be the same as or above the youngest.',
            'height_max_cm.gte' => 'The tallest height needs to be the same as or above the shortest.',
            'core_values.max' => 'Choose up to five values.',
            'love_languages.max' => 'Choose up to three.',
            'preferred_qualities.max' => 'Choose up to five.',
        ];
    }
}
