<?php

namespace App\Support;

use App\Models\MatchRecord;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Guardian\GuardianGate;

/**
 * The member's index of everything that is not one of the four tabs.
 *
 * ⚠️ FOUR TABS IS THE RIGHT NUMBER AND THE SURFACE OUTGREW IT.
 *
 * The bottom bar carries Matches, Profile, Packages and Account, and
 * App\Support\Navigation documents that it is four and never five. That rule is
 * correct — a phone-shaped product on a mid-range Android is not improved by a
 * fifth thumb target — but the product kept growing underneath it. Trusted
 * contacts, the support route, purchases and the full introduction history are
 * all real screens, and `/introductions` was reachable from no member screen at
 * all: it worked, it was tested, and there was no way in. Exactly the fault the
 * admin sidebar was built to fix, on the other side of the product.
 *
 * A sidebar is the wrong answer here. On a phone it becomes a hamburger, which
 * puts every destination behind a tap and a mental model, and members open this
 * briefly to do one thing.
 *
 * So the tab bar is untouched and Account becomes a hub: a grouped index where
 * each row carries its own state. "2 waiting" on a row is worth more than any
 * amount of chrome, because it answers the question the tap was going to ask.
 *
 * ⚠️ BUILT ON THE SERVER, FOR THE SAME REASON Navigation IS. A row is shown
 * only when the person can actually open it, decided by the same condition the
 * route enforces. MemberHubTest walks every row for every member state and
 * opens it, so a row that 404s cannot ship.
 */
class MemberHub
{
    /**
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, href: string, icon: string, status: string|null}>}>
     */
    public static function for(User $user): array
    {
        $profile = $user->profile;
        $policy = app(GuardianPolicy::class);
        $gender = $profile?->gender;

        $groups = [
            [
                'key' => 'matches',
                'label' => 'Your introductions',
                'items' => [
                    [
                        'key' => 'introductions',
                        'label' => 'All introductions',
                        'href' => '/introductions',
                        'icon' => 'matches',
                        /*
                         * ⚠️ THE ROW THAT HAD NO WAY IN. Everything else here
                         * was reachable from somewhere; this was reachable from
                         * nowhere.
                         */
                        'status' => self::introductionStatus($user),
                        'visible' => true,
                    ],
                    [
                        'key' => 'purchases',
                        'label' => 'Purchases and receipts',
                        'href' => '/purchases',
                        'icon' => 'packages',
                        'status' => null,
                        /*
                         * ⚠️ SHOWN DURING THE FREE PERIOD TOO, AND THAT IS
                         * DELIBERATE. Nothing is being charged, but a receipt
                         * for something bought before it started is exactly
                         * what somebody comes looking for, and hiding the only
                         * route to it because prices are currently off would
                         * lose it.
                         */
                        'visible' => true,
                    ],
                ],
            ],

            [
                'key' => 'people',
                'label' => 'Who looks out for you',
                'items' => [
                    [
                        'key' => 'trusted-contact',
                        'label' => 'Your trusted contact',
                        'href' => '/trusted-contact',
                        'icon' => 'users',
                        'status' => self::trustedContactStatus($user, $policy, $gender),
                        // The route 404s when the policy does not offer it, so
                        // the row asks the policy rather than the gender.
                        'visible' => $policy->isOfferedTo($gender),
                    ],
                ],
            ],

            [
                'key' => 'help',
                'label' => 'If something is wrong',
                'items' => [
                    [
                        'key' => 'support',
                        'label' => 'Help and support',
                        'href' => '/support',
                        'icon' => 'lifebuoy',
                        'status' => self::supportStatus($user),
                        'visible' => true,
                    ],
                    [
                        'key' => 'questions',
                        'label' => 'Common questions',
                        'href' => '/questions',
                        'icon' => 'eye',
                        'status' => null,
                        'visible' => true,
                    ],
                ],
            ],
        ];

        return array_values(array_filter(array_map(
            fn (array $group) => [
                'key' => $group['key'],
                'label' => $group['label'],
                'items' => array_values(array_map(
                    fn (array $item) => collect($item)->except('visible')->all(),
                    array_filter($group['items'], fn (array $item) => $item['visible']),
                )),
            ],
            $groups,
        // A group whose every row is hidden must not render as a bare heading.
        ), fn (array $group) => $group['items'] !== []));
    }

    /**
     * ⚠️ THE STATE, NOT THE COUNT, WHEN THERE IS SOMETHING TO DO.
     *
     * "3 introductions" is a fact. "1 waiting to be opened" is the reason to
     * tap, and it is the only one of the two worth the width.
     */
    private static function introductionStatus(User $user): ?string
    {
        $profileId = $user->profile?->id;

        if (! $profileId) {
            return null;
        }

        $unopened = MatchRecord::where('seeker_profile_id', $profileId)
            ->whereNotNull('delivered_at')
            ->whereNull('opened_at')
            ->count();

        if ($unopened > 0) {
            return $unopened.' waiting to be opened';
        }

        $total = MatchRecord::where('seeker_profile_id', $profileId)
            ->whereNotNull('delivered_at')
            ->count();

        return $total > 0 ? $total.' so far' : null;
    }

    private static function trustedContactStatus(User $user, GuardianPolicy $policy, ?string $gender): ?string
    {
        $state = app(GuardianGate::class)->stateFor($user);

        /*
         * ⚠️ THE GATE'S OWN SENTENCE, NOT A SECOND ONE WRITTEN HERE. It already
         * says why somebody is waiting and what would move it; paraphrasing it
         * on this row would give the member two descriptions of one situation
         * that could drift apart.
         *
         * It is trimmed to a phrase because this is a row, not a paragraph —
         * the full sentence is on the page the row opens.
         */
        /*
         * ⚠️ THESE ARE THE GATE'S ACTUAL STATE NAMES, READ OFF IT.
         *
         * I first wrote `awaiting_acceptance` and `awaiting_verification` from
         * memory of how the flow works. Neither exists — the gate emits
         * `awaiting` for both — so every waiting member would have fallen
         * through to `default` and the row would have shown no status at all,
         * silently, on the one row where waiting is the whole point.
         */
        return match ($state['state'] ?? null) {
            'none_named' => $policy->isRequiredFor($gender) ? 'Needed before we can introduce you' : 'Not added yet',
            'awaiting' => 'Waiting on them, or on us',
            'stale' => 'Nothing has happened for a while',
            'verified' => 'In place',
            'rejected' => 'Needs another look',
            default => null,
        };
    }

    private static function supportStatus(User $user): ?string
    {
        $open = SupportTicket::where('user_id', $user->id)
            ->where('status', 'open')
            ->count();

        return $open > 0
            ? $open.' open with us'
            : null;
    }
}
