<?php

namespace App\Services\Credits;

use App\Exceptions\InsufficientCredits;
use App\Exceptions\MatchAlreadyUnlocked;
use App\Models\CreditLedgerEntry;
use App\Models\MatchRecord;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Every credit that is ever granted or spent goes through here.
 *
 * ⚠️ THE CORRECTNESS GUARANTEE DOES NOT LIVE IN THE LOCK.
 *
 * lockForUpdate() is an ergonomics tool: it makes the common case produce a
 * clean error rather than a caught exception. What actually makes
 * double-spending impossible is a pair of unique indexes, which hold whether
 * or not the lock did anything — and the lock does nothing at all on SQLite,
 * where the fast test suite runs.
 *
 *   granting  — UNIQUE(gateway_transaction_id)
 *   spending  — UNIQUE(reason, reference_type, reference_id)
 *
 * Build it so the row that must not exist twice cannot exist twice, then put
 * the lock on top for the error message.
 *
 * ⚠️ NOTHING HERE MAY EVER UPDATE OR DELETE A LEDGER ROW. A correction is a
 * new row with an opposing delta. The history of a member's money stays
 * readable end to end, and `realvows:verify-ledger` can walk the chain and
 * prove it.
 */
class CreditService
{
    /**
     * Grant credits for a completed purchase.
     *
     * Idempotent by construction: a retried gateway callback hits the unique
     * index on gateway_transaction_id and returns the existing row rather
     * than crediting twice.
     */
    public function grantForPurchase(Purchase $purchase): CreditLedgerEntry
    {
        if (! $purchase->gateway_transaction_id) {
            throw new \InvalidArgumentException(
                'Refusing to grant credits without a gateway transaction id — it is the only thing '
                .'that makes the grant idempotent.'
            );
        }

        return DB::transaction(function () use ($purchase) {
            $this->lockUser($purchase->user_id);

            $existing = CreditLedgerEntry::where('gateway_transaction_id', $purchase->gateway_transaction_id)->first();

            if ($existing) {
                // A duplicate callback. Normal, not exceptional.
                Log::info('Credit grant already applied; ignoring duplicate.', [
                    'purchase_id' => $purchase->id,
                    'ledger_id' => $existing->id,
                ]);

                return $existing;
            }

            $balance = $this->balanceInTransaction($purchase->user_id);

            try {
                $entry = CreditLedgerEntry::create([
                    'user_id' => $purchase->user_id,
                    'delta' => $purchase->credits_granted,
                    'balance_after' => $balance + $purchase->credits_granted,
                    'reason' => 'purchase',
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'gateway_transaction_id' => $purchase->gateway_transaction_id,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                /*
                 * Two callbacks arriving at the same instant. The index did
                 * its job; read back the row the other one wrote.
                 */
                Log::info('Concurrent duplicate credit grant blocked by the unique index.', [
                    'purchase_id' => $purchase->id,
                ]);

                return CreditLedgerEntry::where('gateway_transaction_id', $purchase->gateway_transaction_id)->firstOrFail();
            }

            $purchase->forceFill([
                'status' => Purchase::STATUS_COMPLETED,
                'completed_at' => now(),
            ])->save();

            return $entry;
        });
    }

    /**
     * Spend one credit to open one introduction.
     *
     * Two tabs clicking Unlock with one credit left must produce exactly one
     * unlock and one kind error — never two unlocks, and never a lost update
     * that gives away a free match.
     */
    /**
     * Open an introduction during the free period, charging nothing.
     *
     * ⚠️ IT STILL WRITES A LEDGER ROW, WITH A ZERO DELTA.
     *
     * Skipping the ledger because no money moved is the tempting shortcut and
     * it breaks three things at once: `verify-ledger` starts reporting unlocked
     * matches with no entry as drift, nobody can count how many introductions
     * were given away, and after the cutover there is no way to tell a free
     * introduction from a paid one — which is the distinction the permanence
     * guarantee rests on.
     *
     * Zero because nothing was charged. Present because something happened.
     *
     * ⚠️ The stamp on the match is what makes the grant permanent. See
     * FreePeriod::wasGrantedFree — it must be answerable from the row long
     * after the mode has changed.
     */
    public function unlockMatchFree(User $user, MatchRecord $match): CreditLedgerEntry
    {
        if ($match->isUnlocked()) {
            throw new MatchAlreadyUnlocked;
        }

        return DB::transaction(function () use ($user, $match) {
            $this->lockUser($user->id);

            $fresh = MatchRecord::whereKey($match->id)->firstOrFail();

            if ($fresh->status === MatchRecord::STATUS_UNLOCKED) {
                throw new MatchAlreadyUnlocked;
            }

            $balance = $this->balanceInTransaction($user->id);

            $entry = CreditLedgerEntry::create([
                'user_id' => $user->id,
                'delta' => 0,
                'balance_after' => $balance,
                'reason' => \App\Support\FreePeriod::LEDGER_REASON,
                'reference_type' => MatchRecord::class,
                'reference_id' => $match->id,
            ]);

            $fresh->forceFill([
                'status' => MatchRecord::STATUS_UNLOCKED,
                'unlocked_at' => now(),
                'free_period_granted_at' => now(),
                'unlocked_by_ledger_id' => $entry->id,
            ])->save();

            $match->refresh();

            return $entry;
        });
    }

    public function unlockMatch(User $user, MatchRecord $match): CreditLedgerEntry
    {
        if ($match->isUnlocked()) {
            throw new MatchAlreadyUnlocked;
        }

        return DB::transaction(function () use ($user, $match) {
            $this->lockUser($user->id);

            /*
             * ⚠️ ORDER MATTERS, AND NOT FOR CORRECTNESS — FOR HONESTY.
             *
             * The status is checked BEFORE the balance. Both orders spend
             * exactly one credit and both refuse the second attempt, so the
             * guarantee is identical either way. But with the balance first,
             * somebody who double-tapped their last credit is told "you have
             * no introductions left" — which reads as though the second tap
             * cost them something. It did not. They need to hear that.
             *
             * Re-read inside the transaction: the other tab may have won
             * between the check at the top of this method and getting here.
             */
            $fresh = MatchRecord::whereKey($match->id)->firstOrFail();

            if ($fresh->status === MatchRecord::STATUS_UNLOCKED) {
                throw new MatchAlreadyUnlocked;
            }

            $balance = $this->balanceInTransaction($user->id);

            if ($balance < 1) {
                throw new InsufficientCredits;
            }

            try {
                $entry = CreditLedgerEntry::create([
                    'user_id' => $user->id,
                    'delta' => -1,
                    'balance_after' => $balance - 1,
                    'reason' => 'match_unlock',
                    'reference_type' => MatchRecord::class,
                    'reference_id' => $match->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                /*
                 * ⚠️ THE GUARANTEE FIRING. The other tab got there first and
                 * the unique index refused the second charge. Nobody was
                 * double-charged; this one simply lost the race.
                 */
                throw new MatchAlreadyUnlocked;
            }

            $fresh->forceFill([
                'status' => MatchRecord::STATUS_UNLOCKED,
                'unlocked_at' => now(),
                'unlocked_by_ledger_id' => $entry->id,
                'delivered_at' => $fresh->delivered_at ?? now(),
            ])->save();

            return $entry;
        });
    }

    /**
     * A staff adjustment. Reason is mandatory, and it writes an ordinary
     * ledger row — there is no balance column to edit, by design.
     */
    public function adjust(User $user, int $delta, string $reason, string $note, User $by): CreditLedgerEntry
    {
        if (! in_array($reason, ['admin_adjustment', 'goodwill'], true)) {
            throw new \InvalidArgumentException("[{$reason}] is not an adjustment reason.");
        }

        if (trim($note) === '') {
            throw new \InvalidArgumentException('An adjustment must say why. That is the whole point of the audit trail.');
        }

        return DB::transaction(function () use ($user, $delta, $reason, $note, $by) {
            $this->lockUser($user->id);

            $balance = $this->balanceInTransaction($user->id);

            if ($balance + $delta < 0) {
                throw new InsufficientCredits(
                    "That would take the balance to ".($balance + $delta).'. Credits cannot go negative.'
                );
            }

            return CreditLedgerEntry::create([
                'user_id' => $user->id,
                'delta' => $delta,
                'balance_after' => $balance + $delta,
                'reason' => $reason,
                'note' => $note,
                'created_by' => $by->id,
            ]);
        });
    }

    /**
     * Give a credit back — used when an unlock has to be undone.
     * A new positive row, never a deletion of the negative one.
     */
    public function reverseUnlock(MatchRecord $match, string $note): ?CreditLedgerEntry
    {
        $original = $match->ledgerEntry;

        if (! $original) {
            return null;
        }

        return DB::transaction(function () use ($match, $original, $note) {
            $this->lockUser($original->user_id);

            $balance = $this->balanceInTransaction($original->user_id);

            $entry = CreditLedgerEntry::create([
                'user_id' => $original->user_id,
                'delta' => 1,
                'balance_after' => $balance + 1,
                'reason' => 'unlock_reversed',
                'reference_type' => MatchRecord::class,
                'reference_id' => $match->id,
                'note' => $note,
            ]);

            $match->forceFill([
                'status' => MatchRecord::STATUS_TEASER,
                'unlocked_at' => null,
                'unlocked_by_ledger_id' => null,
            ])->save();

            return $entry;
        });
    }

    /**
     * ⚠️ Read INSIDE the transaction, always. A balance read before the
     * transaction opened is a balance that may already be wrong.
     */
    private function balanceInTransaction(int $userId): int
    {
        return (int) CreditLedgerEntry::where('user_id', $userId)->sum('delta');
    }

    /**
     * Serialises concurrent spending on one account.
     *
     * A no-op on SQLite, which is exactly why the unique indexes carry the
     * guarantee rather than this.
     */
    private function lockUser(int $userId): void
    {
        DB::table('users')->where('id', $userId)->lockForUpdate()->first();
    }
}
