<?php

namespace App\Support;

use App\Services\Risk\RiskNarrator;
use App\Services\Settings\SettingsService;

/**
 * Which AI provider is actually live, asked of the thing that would use it.
 *
 * ⚠️ IT ASKS THE CONTAINER, IT DOES NOT RE-DERIVE THE RULE.
 *
 * Same argument as the gateway banner. The tempting version reads the settings
 * table and reasons: "there is a key, the provider is openai, therefore the AI
 * features are live." That is a SECOND implementation of a decision made
 * elsewhere, and two implementations of one rule eventually disagree — at which
 * point the banner confidently reports a state the product is not in, which is
 * worse than having no banner at all.
 *
 * So it resolves the object that does the work and asks it. If the narrator
 * says it is not configured, the banner says off, whatever the settings table
 * appears to contain.
 */
class AiProviderStatus
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly RiskNarrator $narrator,
    ) {}

    /** @return array<string, mixed> */
    public function forDisplay(): array
    {
        /*
         * ⚠️ THE LIVE ANSWER COMES FROM THE NARRATOR, NOT FROM THE SETTINGS.
         * Everything else here is descriptive; this one line is the claim.
         */
        $live = $this->narrator->isConfigured();

        return [
            'live' => $live,
            'provider' => $live ? $this->setting('provider', 'openai') : null,
            'riskModel' => $this->setting('risk_model'),
            'writerModel' => $this->setting('writer_model'),

            /*
             * ⚠️ WHAT STILL WORKS WHEN IT IS OFF, said explicitly. An operator
             * reading "AI disabled" needs to know whether verification has
             * stopped. It has not: the rules run regardless and only the
             * written summary is missing.
             */
            'riskFallsBackToRules' => true,
            'writerHidden' => ! $live,

            'note' => $live
                ? null
                : 'No API key is stored, so both AI features are off. Risk scoring still runs '
                    .'its rules and the profile writer hides its button. Nothing is blocked.',
        ];
    }

    private function setting(string $key, ?string $default = null): ?string
    {
        try {
            $value = trim((string) $this->settings->get('ai', $key, (string) $default));
        } catch (\Throwable) {
            return $default;
        }

        return $value === '' ? $default : $value;
    }
}
