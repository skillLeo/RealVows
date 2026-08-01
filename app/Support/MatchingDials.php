<?php

namespace App\Support;

use App\Services\Settings\SettingsService;

/**
 * The two matching numbers the owner is allowed to turn.
 *
 * Settings is the source of truth; config/matching.php supplies the default
 * and the bounds. Everything is clamped on read as well as validated on
 * write, because a value can also arrive by someone editing the database
 * directly, and a threshold of 5 would introduce members to strangers and
 * call it matching.
 *
 * The WEIGHTS are deliberately not here. They define the product and live
 * only in config/matching.php, displayed read-only in the admin panel.
 */
class MatchingDials
{
    public function __construct(private readonly SettingsService $settings) {}

    public function threshold(): int
    {
        return $this->clamped('minimum_score_threshold', 'threshold');
    }

    public function teaserCount(): int
    {
        return $this->clamped('matches_per_batch', 'teaser_count');
    }

    private function clamped(string $settingKey, string $configKey): int
    {
        $bounds = config("matching.{$configKey}");
        $value = $this->settings->int('matching', $settingKey, $bounds['default']);

        return max($bounds['min'], min($bounds['max'], $value));
    }

    /**
     * The weights, for read-only display. Returned in descending order so the
     * admin panel shows what matters most first without sorting in the view.
     *
     * @return array<string, int>
     */
    public static function weights(): array
    {
        $weights = config('matching.weights');
        arsort($weights);

        return $weights;
    }
}
