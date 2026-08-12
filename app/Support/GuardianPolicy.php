<?php

namespace App\Support;

use App\Services\Settings\SettingsService;

/**
 * How much the trusted-contact system is required, and of whom.
 *
 * ── ⚠️ A SETTING, NOT A CODE PATH, AND THAT IS THE WHOLE DESIGN ───────────
 *
 * There is a genuine unresolved disagreement here. The client does not want
 * this presented to men and believes many women will not have anybody to name.
 * Compliance requires it. Both positions are reasonable and the argument is not
 * settled.
 *
 * Building it as a setting is what makes it buildable while the argument is
 * still open. Every gate in the system reads this one value, so the policy can
 * be changed by a person, in the open, without a deploy — and nobody has to
 * pick a winner today by writing one answer into a branch.
 *
 * ⚠️ THE DEFAULT IS `off` AND IT IS NOT A DECISION. It is the absence of one.
 * Shipping `required_all` would quietly resolve the disagreement in code and
 * present it as an implementation detail. Shipping `off` leaves the product
 * behaving exactly as it does today until somebody chooses otherwise, and the
 * settings screen says so in those words.
 */
class GuardianPolicy
{
    public const GROUP = 'guardian';

    public const KEY = 'guardian_requirement';

    public const REQUIRED_ALL = 'required_all';

    public const REQUIRED_FEMALE = 'required_female';

    public const OPTIONAL = 'optional';

    public const OFF = 'off';

    /** @return list<string> */
    public static function levels(): array
    {
        return [self::REQUIRED_ALL, self::REQUIRED_FEMALE, self::OPTIONAL, self::OFF];
    }

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * ⚠️ UNREADABLE SETTINGS MEAN `off`, NOT `required_all`.
     *
     * Failing closed sounds right and is wrong here. Defaulting to required
     * during a database fault would lock every member out of the matching pool
     * until somebody noticed, which is a worse outcome than the system being
     * briefly less strict than intended. The failure is logged so it is not
     * silent.
     */
    public function level(): string
    {
        try {
            $value = (string) $this->settings->get(self::GROUP, self::KEY, self::OFF);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('guardian requirement unreadable', [
                'error' => $e->getMessage(),
                'consequence' => 'Treating the trusted-contact requirement as off. Members are '
                    .'not being blocked from the pool.',
            ]);

            return self::OFF;
        }

        return in_array($value, self::levels(), true) ? $value : self::OFF;
    }

    /** Whether this member must have a verified trusted contact to be matched. */
    public function isRequiredFor(?string $gender): bool
    {
        return match ($this->level()) {
            self::REQUIRED_ALL => true,
            self::REQUIRED_FEMALE => in_array($gender, ['woman', 'non_binary'], true),
            default => false,
        };
    }

    /** Whether a member may nominate one even when it is not required. */
    public function isOfferedTo(?string $gender): bool
    {
        return $this->level() !== self::OFF || $this->isRequiredFor($gender);
    }

    public function isOff(): bool
    {
        return $this->level() === self::OFF;
    }
}
