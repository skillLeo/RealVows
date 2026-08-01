<?php

namespace App\Support;

use App\Services\Settings\SettingsService;

/**
 * The three packages, resolved from settings with code-level fallbacks.
 *
 * Prices and credit counts are the owner's to change, which is safe here in
 * a way it would not be elsewhere: every purchase snapshots the price and
 * credit count onto its own row, so changing a price never rewrites history.
 *
 * The KEYS are not the owner's to change — they are in the database enum, in
 * existing rows and in the checkout URLs.
 */
class Packages
{
    /** Ordered as they appear on the packages screen. `triple` is the raised card. */
    public const KEYS = ['single', 'triple', 'octet'];

    public const DEFAULTS = [
        'single' => ['credits' => 1, 'price_cents' => 2000, 'label' => 'One introduction', 'badge' => ''],
        'triple' => ['credits' => 3, 'price_cents' => 5000, 'label' => 'Three introductions', 'badge' => 'Most chosen'],
        'octet' => ['credits' => 8, 'price_cents' => 10000, 'label' => 'Eight introductions', 'badge' => 'Best value'],
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array<string, array{key: string, credits: int, price_cents: int, label: string, badge: string, price: string}>
     */
    public function all(): array
    {
        $out = [];

        foreach (self::KEYS as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function get(string $key): array
    {
        if (! in_array($key, self::KEYS, true)) {
            throw new \InvalidArgumentException("Unknown package [{$key}].");
        }

        $default = self::DEFAULTS[$key];

        /*
         * Settings are strings and may be blank or nonsense. A zero price
         * would mean giving credits away, and a zero credit count would mean
         * charging for nothing — so both fall back rather than trusting the
         * stored value. The save path validates too; this is the second net.
         */
        $credits = $this->settings->int('packages', "{$key}_credits");
        $price = $this->settings->int('packages', "{$key}_price_cents");

        return [
            'key' => $key,
            'credits' => $credits > 0 ? $credits : $default['credits'],
            'price_cents' => $price > 0 ? $price : $default['price_cents'],
            'label' => $this->settings->get('packages', "{$key}_label") ?: $default['label'],
            'badge' => $this->settings->get('packages', "{$key}_badge") ?: $default['badge'],
            'price' => '$'.number_format(($price > 0 ? $price : $default['price_cents']) / 100, 0),
        ];
    }
}
