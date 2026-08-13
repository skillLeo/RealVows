<?php

namespace App\Support;

/**
 * Plain sentences about what the numbers mean, for a non-technical reader.
 *
 * ⚠️ THIS IS DELIBERATELY NOT AI, AND THE REASON IS NOT COST.
 *
 * The obvious build is to post the figures to a model and print what comes
 * back. It would read well. It is the wrong tool here, for four reasons that
 * all matter more than the API bill:
 *
 *   1. A MODEL CAN GET A NUMBER WRONG. Every sentence below quotes a figure
 *      that is also drawn on the same screen. A model that writes "about a
 *      dozen people are waiting" next to a tile reading 3 has not been
 *      unhelpful, it has made the whole dashboard untrustworthy — and the
 *      client cannot check it, which is exactly why he is reading a summary.
 *
 *   2. THE RULES ARE THE PRODUCT'S OWN JUDGEMENT. "Somebody has waited five
 *      days" being urgent is a decision about how this business treats people
 *      who submitted a photograph. That belongs in code the client can be
 *      shown, argued with and changed — not in a prompt whose behaviour drifts
 *      with the model version.
 *
 *   3. IT WOULD BE THE ONLY PART OF THE DASHBOARD THAT CAN FAIL. Everything
 *      else is a COUNT(*). Adding a network call means the overview has a
 *      spinner, a timeout, and a state where the numbers are there and the
 *      explanation is not.
 *
 *   4. THERE IS NO KEY. The client has not supplied one, so an AI panel would
 *      ship dark and look broken on the screen he opens first.
 *
 * Cost is real too — this runs on every dashboard load — but it is the last
 * reason, not the first. If the client later wants prose that genuinely needs a
 * model (a monthly written summary he can forward), that is a different feature
 * with a different failure mode, and it can sit behind ProfileWriter's existing
 * key check rather than on the critical path of the first screen.
 */
class DashboardInsights
{
    /** A person waiting longer than this has been waiting too long. */
    public const SLOW_VERIFICATION_DAYS = 3;

    /** Below this, a profile will never be introduced to anybody. */
    public const USABLE_COMPLETENESS = 50;

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{tone: string, text: string, href: string|null}>
     */
    public static function from(array $data): array
    {
        $insights = [];

        $queues = $data['queues'];
        $members = $data['members'];
        $introductions = $data['introductions'];

        /*
         * ⚠️ THE PERSON WAITING LONGEST COMES FIRST, ALWAYS.
         *
         * Everything else on this page is about the platform. This one is
         * about somebody who sent us a photograph of their face and has heard
         * nothing since, which is the only item here with a clock running on
         * somebody else's patience.
         */
        if (($queues['oldestWaitingDays'] ?? null) !== null && $queues['oldestWaitingDays'] >= self::SLOW_VERIFICATION_DAYS) {
            $days = $queues['oldestWaitingDays'];

            $insights[] = [
                'tone' => 'urgent',
                'text' => "Somebody has been waiting {$days} days to be verified. They cannot be introduced to anybody until you look at it.",
                'href' => '/admin/verifications',
            ];
        } elseif ($queues['verifications'] > 0) {
            $insights[] = [
                'tone' => 'attention',
                'text' => $queues['verifications'].' '.self::plural($queues['verifications'], 'person is', 'people are').' waiting to be verified.',
                'href' => '/admin/verifications',
            ];
        }

        if ($queues['reports'] > 0) {
            $insights[] = [
                'tone' => 'urgent',
                'text' => $queues['reports'].' '.self::plural($queues['reports'], 'report has', 'reports have').' been made about a member and '.self::plural($queues['reports'], 'is', 'are').' unread.',
                'href' => '/admin/reports',
            ];
        }

        if ($queues['support'] > 0) {
            $insights[] = [
                'tone' => 'attention',
                'text' => $queues['support'].' '.self::plural($queues['support'], 'member has', 'members have').' written to support and had no reply.',
                'href' => '/admin/support',
            ];
        }

        if ($queues['trustedContacts'] > 0) {
            $insights[] = [
                'tone' => 'attention',
                'text' => $queues['trustedContacts'].' trusted '.self::plural($queues['trustedContacts'], 'contact is', 'contacts are').' waiting to be checked. Their members cannot be introduced until they are.',
                'href' => '/admin/trusted-contacts',
            ];
        }

        /*
         * ⚠️ EXPRESSED AS PEOPLE WHO CANNOT BE MATCHED, NOT AS A PERCENTAGE.
         *
         * "48% of profiles are under half complete" is a statistic. "Fourteen
         * people signed up and will never be introduced to anybody" is the same
         * fact and is the one that gets acted on.
         */
        $unusable = collect($data['completeness'] ?? [])
            ->filter(fn (array $band) => in_array($band['label'], ['Under 25%', '25–49%'], true))
            ->sum('count');

        if ($unusable > 0 && $members['total'] > 0) {
            $insights[] = [
                'tone' => 'attention',
                'text' => "{$unusable} ".self::plural($unusable, 'member has', 'members have').' a profile under half finished. Nobody at that stage can be introduced to anybody.',
                'href' => null,
            ];
        }

        /*
         * Delivered but unopened. Only worth saying once there is enough to be
         * a pattern rather than a coincidence — three deliveries proving
         * nothing either way.
         */
        if ($introductions['delivered'] >= 5) {
            $unopened = $introductions['delivered'] - $introductions['opened'];
            $share = (int) round(($unopened / $introductions['delivered']) * 100);

            if ($share >= 50) {
                $insights[] = [
                    'tone' => 'attention',
                    'text' => "{$share}% of introductions that were delivered have not been opened. The notification may not be reaching people.",
                    'href' => null,
                ];
            }
        }

        $joinedRecently = collect($data['signups'] ?? [])->take(-7)->sum('count');

        if ($joinedRecently === 0 && $members['total'] > 0) {
            $insights[] = [
                'tone' => 'attention',
                'text' => 'Nobody has joined in the last seven days.',
                'href' => null,
            ];
        }

        /*
         * ⚠️ SAYING "ALL CLEAR" IS A FEATURE, NOT FILLER. A panel that is empty
         * when nothing is wrong is indistinguishable from a panel that is
         * broken, and the client would learn to ignore it either way.
         */
        if ($insights === []) {
            $insights[] = [
                'tone' => 'good',
                'text' => $members['total'] === 0
                    ? 'Nothing to do yet. Nobody has joined.'
                    : 'Nothing needs you right now. Every queue is clear and nobody is waiting.',
                'href' => null,
            ];
        }

        return $insights;
    }

    private static function plural(int $count, string $one, string $many): string
    {
        return $count === 1 ? $one : $many;
    }
}
