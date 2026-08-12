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
     * ══════════════════════════════════════════════════════════════════════
     * ⚠️ DO NOT "HARDEN" THIS. IT FAILS OPEN ON PURPOSE.
     * ══════════════════════════════════════════════════════════════════════
     *
     * An unreadable setting returns `off`, which means the trusted-contact
     * requirement is not enforced. That is fail-OPEN on what is fundamentally
     * a compliance control, and it will look like a bug to the next person who
     * reads it. It is not. It is the considered answer and the reasoning is
     * here so nobody has to reconstruct it under pressure.
     *
     * The tempting change is `return self::REQUIRED_ALL` in the catch block:
     * if we cannot tell whether the requirement applies, assume it does. That
     * is the right instinct for most controls and the wrong one for this one.
     *
     * ── WHAT FAILING CLOSED WOULD ACTUALLY DO ─────────────────────────────
     *
     * This is read on the path that decides whether a member enters the
     * matching pool. A database fault, a settings table mid-migration, a cache
     * driver hiccup — any of them would make every member on the platform
     * suddenly require a verified trusted contact they have not been asked for
     * and cannot supply in the moment. Nobody would be matched. Nobody would
     * see an error, because from the member's side the product would simply
     * have gone quiet. It would be discovered days later, and every
     * introduction not made in between is gone.
     *
     * Failing open costs the opposite: for the duration of the fault, members
     * enter the pool who should have been gated. That is recoverable. The
     * gate is re-applied the moment settings are readable, the affected
     * accounts are identifiable, and nothing irreversible has happened —
     * introductions still require the guardian's approval at the other two
     * gates in B5, which read the same setting and will hold once it is back.
     *
     * One failure mode is silent and permanent; the other is loud, brief and
     * reversible. That is the whole argument.
     *
     * ⚠️ AND IT IS LOGGED AT WARNING, NOT SWALLOWED. A control that quietly
     * stops applying is indistinguishable from one that was never configured.
     * If this line appears in the log, the requirement is not being enforced
     * right now and somebody needs to know today rather than at the next audit.
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
