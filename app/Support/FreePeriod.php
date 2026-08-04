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
            /*
             * ⚠️ ALERTED, NOT ONLY LOGGED.
             *
             * "Visible and refundable" only holds if somebody NOTICES. A
             * member charged during a free period does not think "that must be
             * a bug" — they think the free period was a lie, and they tell
             * people that. A log line nobody reads for a week is not the
             * safety net the fail-closed decision assumed.
             */
            \Illuminate\Support\Facades\Log::critical('free period unreadable, charging members', [
                'error' => $e->getMessage(),
                'consequence' => 'Introductions are being charged for. If the free period is '
                    .'meant to be running, this is a live billing fault happening right now.',
                'action' => 'Check the settings table and the cache, then refund anybody charged '
                    .'since this started.',
            ]);

            $this->alertOnce($e);

            return false;
        }
    }

    /**
     * ⚠️ ASKED OF THE MATCH, NOT OF THE CLOCK. DO NOT "HARDEN" THIS.
     *
     * This will look like duplicated state. There is a free-period setting and
     * a free-period start date, so a stamp on every row reads as denormalised
     * — and deriving it instead ("granted before the cutover date") looks like
     * a tidy-up that removes redundancy. The diff would be small and would
     * pass every test that does not change the setting mid-run.
     *
     * It is not redundancy. A derived answer is computed against a value
     * somebody can EDIT, so the moment the cutover date moves, every free
     * member's introduction silently becomes "paid" — or worse, "not yet
     * granted" — and the permanence guarantee evaporates without a single
     * error. That is the retroactive re-terms bug wearing a different costume,
     * and it is the exact thing this whole design exists to prevent.
     *
     * A stamp is a fact about what happened. A derivation is an opinion about
     * what happened, recalculated every time somebody touches a setting.
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
     * ⚠️ THROTTLED TO ONE ALERT AN HOUR.
     *
     * This fires from a code path that runs on EVERY request. Without a
     * throttle a settings outage sends thousands of identical emails, which
     * gets the sending domain marked as spam — so the alert about a billing
     * fault would take out the channel that delivers introductions.
     *
     * The cache key is deliberately not the error message: a changing message
     * would defeat the throttle.
     */
    private function alertOnce(\Throwable $e): void
    {
        try {
            $cache = \Illuminate\Support\Facades\Cache::class;

            if (! $cache::add('realvows.free-period-alert', true, now()->addHour())) {
                return;
            }

            $to = $this->settings->get('general', 'admin_notification_email');

            if (! $to) {
                return;
            }

            \Illuminate\Support\Facades\Mail::raw(
                "The free-period setting could not be read, so RealVows is charging for "
                    ."introductions.\n\n"
                    ."If the free period is supposed to be running, members are being charged "
                    ."right now for something that should be free.\n\n"
                    ."Error: ".$e->getMessage()."\n\n"
                    ."Check the settings table and the cache, then refund anybody charged since "
                    ."this began.",
                fn ($m) => $m->to($to)->subject('RealVows: free period unreadable — members are being charged'),
            );
        } catch (\Throwable) {
            /*
             * ⚠️ SWALLOWED ON PURPOSE, AND ONLY HERE.
             *
             * If mail is also down, an exception thrown while reporting a
             * settings failure would turn a billing fault into a total
             * outage — the alert taking down the site it is warning about.
             * The critical log line above has already been written.
             */
        }
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
