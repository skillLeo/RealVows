<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const CACHE_KEY = 'realvows.settings';

    /**
     * @var array<string, string|null>|null
     */
    private ?array $resolved = null;

    /**
     * Every setting, decrypted, keyed "group.key".
     *
     * Read through the cache so a settings lookup never costs a query on a
     * normal request — this matters on shared hosting, where the database is
     * the slowest thing we touch. Also memoised per request, so twenty
     * lookups in one request cost one cache read.
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        return $this->resolved = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()
                ->mapWithKeys(fn (Setting $setting) => [
                    "{$setting->group}.{$setting->key}" => $setting->plainValue(),
                ])
                ->all();
        });
    }

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return $this->all()["{$group}.{$key}"] ?? $default;
    }

    public function bool(string $group, string $key, bool $default = false): bool
    {
        $value = $this->get($group, $key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function int(string $group, string $key, int $default = 0): int
    {
        $value = $this->get($group, $key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    /**
     * Definitions for one group, ready for the settings screen.
     *
     * Secrets leave as a mask — eight bullets and the last four characters —
     * so the client can recognise which key is stored without the value ever
     * reaching the browser in full.
     */
    public function groupForDisplay(string $group): Collection
    {
        return Setting::query()
            ->where('group', $group)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Setting $setting) => [
                'key' => $setting->key,
                'label' => $setting->label,
                'description' => $setting->description,
                'type' => $setting->type,
                'options' => $setting->options,
                'is_secret' => $setting->is_secret,
                'value' => $setting->maskedValue(),
                'has_value' => $setting->value !== null,
            ]);
    }

    /**
     * Save one value.
     *
     * Returns false when nothing changed — which is how an unchanged masked
     * secret is protected. The settings screen sends back the mask it was
     * given for any field the client did not touch; writing that mask would
     * destroy the real credential, so it is rejected here rather than being
     * relied on further up.
     */
    public function set(string $group, string $key, ?string $value): bool
    {
        $setting = Setting::where('group', $group)->where('key', $key)->first();

        if (! $setting) {
            return false;
        }

        if ($setting->is_secret && $this->looksLikeMask($value)) {
            return false;
        }

        if ($setting->plainValue() === $value) {
            return false;
        }

        $setting->setPlainValue($value);
        $setting->save();

        $this->flush();

        return true;
    }

    public function looksLikeMask(?string $value): bool
    {
        return $value !== null && str_starts_with($value, '••••');
    }

    public function flush(): void
    {
        $this->resolved = null;
        Cache::forget(self::CACHE_KEY);
    }
}
