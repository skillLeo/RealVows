<?php

namespace App\Support;

use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Log;

/**
 * ⚠️ ONE PREDICATE, FOUR INPUTS, ALL OF WHICH MUST AGREE.
 *
 * "Are payments live" was never a single setting, and both of the obvious
 * single sources are unsafe in opposite directions:
 *
 *  · Reading the free period alone means somebody clearing that one flag
 *    renders live purchase buttons against an empty merchant id, and a member
 *    reaches a gateway that cannot take their money.
 *
 *  · Reading merchant credentials alone means the moment the client pastes his
 *    details in to save them, the public pricing page switches itself on while
 *    the free period is still running and nobody intended it.
 *
 * Both are the same failure: a public page making a promise the database
 * contradicts. So every input has a veto, and any one dissenting means not
 * live.
 *
 * ⚠️ THE DANGEROUS MOMENT IS NOT TODAY. Today the answer is unambiguous. It is
 * the day the merchant credentials arrive and somebody switches one flag
 * without checking the other three.
 */
class Payments
{
    /**
     * ⚠️ FAILS TO "NOT LIVE" ON ANY DOUBT, INCLUDING AN UNREADABLE SETTING.
     *
     * Not live means the free-period wording, which is true and costs a member
     * nothing. Live wrongly means a purchase button in front of somebody who
     * cannot be charged, or can be charged while we are telling them it is
     * free.
     */
    public static function areLive(): bool
    {
        try {
            $settings = app(SettingsService::class);

            // 1. The free period must be over. While it runs, nothing is sold.
            if ($settings->bool('general', 'free_period', true)) {
                return false;
            }

            // 2. A gateway must be chosen.
            if (trim((string) $settings->get('payments', 'gateway', '')) === '') {
                return false;
            }

            /*
             * 3. And it must not be pointing at a sandbox. A sandbox that takes
             *    a real card number and settles nothing is the worst of the
             *    four to get wrong, because it looks like success to everybody.
             */
            if (trim((string) $settings->get('payments', 'environment', 'sandbox')) === 'sandbox') {
                return false;
            }

            // 4. And the credentials that actually move money must be present.
            foreach (['merchant_id', 'terminal_id', 'api_key'] as $credential) {
                if (trim((string) $settings->get('payments', $credential, '')) === '') {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Payments state unreadable, treating payments as not live.', [
                'error' => $e->getMessage(),
                'consequence' => 'Public pages show the free-period wording. That is true today '
                    .'and costs a member nothing if it is wrong.',
            ]);

            return false;
        }
    }
}
