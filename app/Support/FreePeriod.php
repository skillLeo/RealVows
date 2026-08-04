<?php

namespace App\Support;

use App\Models\MatchRecord;
use App\Services\Settings\SettingsService;

/**
 * Whether introductions are currently free.
 *
 * ⚠️ A REAL MODE, NOT A GATEWAY LEFT SWITCHED OFF.
 *
 * The client cannot complete his business registration while travelling, so
 * RealVows launches free and payments arrive later. The lazy version of that is
 * to leave the payment gateway unconfigured and hope nobody reaches a checkout.
 * That is not a mode, it is a gap: a member who finds the packages page gets a
 * form that cannot complete, and the product looks broken rather than generous.
 *
 * So this is a state the product genuinely runs in. Introductions arrive
 * unlocked, every purchase route is HIDDEN rather than disabled, and the copy
 * says plainly that introductions are free right now — because silence where a
 * price used to be reads as broken, and a sentence reads as a gift.
 *
 * ── ⚠️ THE EXIT IS THE DIRECTION THAT MATTERS ─────────────────────────────
 *
 * A mode that can be entered and not cleanly left is a trap. When payments come
 * on, members who joined during the free period KEEP every introduction they
 * already received, unlocked, permanently. Only NEW introductions cost credits.
 *
 * Retroactively locking something a member already has changes the terms they
 * joined under. A new platform does not survive that, and no amount of "it was
 * always going to be paid" makes it feel like anything other than a bait.
 *
 * The guarantee is enforced by `matches.free_period_granted_at`, stamped on the
 * row at the moment of granting. Not by comparing dates against a settings
 * value somebody can edit, and not by reading the ledger, which a future
 * migration might archive. FreePeriodTest asserts it survives the mode change.
 */
class FreePeriod
{
    public const SETTING_GROUP = 'general';

    public const SETTING_KEY = 'free_period';

    /** The ledger reason. Distinct so the give-away is countable later. */
    public const LEDGER_REASON = 'free_period_unlock';

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * ⚠️ THIS IS READ ON EVERY REQUEST, INCLUDING PUBLIC PAGES THAT OTHERWISE
     * TOUCH NO DATABASE AT ALL.
     *
     * The Inertia middleware shares it so every screen hides purchase CTAs
     * from one source of truth. That means a settings table which is missing,
     * unmigrated, or unreachable turns the marketing home page into a 500 —
     * a database failure taking down the one page that never needed a
     * database.
     *
     * ⚠️ THE DEFAULT IS "NOT FREE", AND THAT DIRECTION IS DELIBERATE.
     *
     * Failing closed means a broken settings read charges for introductions
     * that should have been free — visible immediately, and refundable.
     * Failing open would give the product away silently and be discovered in
     * a ledger reconciliation weeks later.
     *
     * ⚠️ AND THE FALLBACK IS LOUD. A guard that skips quietly is
     * indistinguishable from one that never fires.
     */
    public function isActive(): bool
    {
        try {
            return $this->settings->bool(self::SETTING_GROUP, self::SETTING_KEY, false);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('free period unreadable, assuming off', [
                'error' => $e->getMessage(),
                'consequence' => 'Introductions are being charged for. If the free period is '
                    .'meant to be running, this is a live billing fault.',
            ]);

            return false;
        }
    }

    /**
     * ⚠️ ASKED OF THE MATCH, NOT OF THE CLOCK.
     *
     * "Was this granted during the free period?" must be answerable long after
     * the free period ends, and must not change when it does. Reading the
     * current mode here — or comparing `created_at` against a start date —
     * would make the answer flip the moment the setting changed, which is
     * exactly the retroactive re-terms this design exists to prevent.
     */
    public function wasGrantedFree(MatchRecord $match): bool
    {
        return $match->free_period_granted_at !== null;
    }

    /**
     * Should a purchase surface be shown at all?
     *
     * ⚠️ HIDDEN, NOT DISABLED. A disabled button still tells a member there is
     * something they are being denied, and a route that 403s still exists to be
     * found. During the free period there is nothing to buy, so there is
     * nothing to show.
     */
    public function purchasesAreAvailable(): bool
    {
        return ! $this->isActive();
    }
}
