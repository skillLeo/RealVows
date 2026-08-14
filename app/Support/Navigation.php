<?php

namespace App\Support;

use App\Http\Controllers\Admin\SettingsController;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The single source of truth for what a signed-in person can navigate to.
 *
 * Built on the server, shared with every Inertia response, and rendered by
 * the desktop bar, the mobile tab bar and the account menu alike — so those
 * three can never drift apart, and none of them can drift from what the
 * routes actually permit.
 *
 * ⚠️ THE RULE THIS CLASS EXISTS TO KEEP: an item's visibility is decided by
 * the SAME permission the route itself enforces. Never by a role.
 *
 * The bug that prompted it: the nav asked "is this person staff?" while the
 * route asked "does this person hold settings.view?". Two different
 * questions, two different answers, and in the gap between them sat a
 * Settings tab that 404'd for a reviewer — a dead link inside the product's
 * own navigation. Roles are how permissions get granted. They are not what
 * anything should be gated on.
 *
 * Adding a nav item is therefore a two-part job: name the permission, and
 * add the route to NavigationTest, which walks every item rendered for every
 * role and asserts each one actually resolves.
 */
class Navigation
{
    /**
     * @return list<array{key: string, label: string, icon: string, href: string}>
     */
    public static function for(?User $user): array
    {
        if (! $user) {
            return [];
        }

        /*
         * A member is defined as someone holding no permissions at all,
         * which is exactly how RolesAndPermissionsSeeder builds the role:
         * members reach their own records through session ownership and
         * policies, never through the permission table. Asking the
         * permission table "do you know this person?" is therefore the
         * permission-shaped way to ask "is this a member?" — and it stays
         * correct if the client is ever given a bespoke role.
         */
        $isMember = $user->getAllPermissions()->isEmpty();

        $items = [
            /*
             * ⚠️ Reports deliberately do NOT get their own tab.
             *
             * The bar is four tabs, never five, and reports live inside the
             * verification area because it is the same job — a person
             * deciding whether somebody belongs here. Adding a fifth tab
             * would have been the easy thing and the wrong one.
             */
            [
                'key' => 'queue',
                'label' => 'Queue',
                'icon' => 'check',
                'href' => '/admin/verifications',
                'visible' => $user->can('verifications.view'),
            ],

            /*
             * Everyone gets Matches. For a member it is their own delivered
             * matches; for staff it is currently the same screen.
             *
             * Phase 2 stage 10 repoints the staff variant at /admin/matches
             * and gates it on `matches.view` — deliberately not done ahead of
             * time, because a nav item pointing at a route that does not
             * exist yet is the exact defect this class was written to stop.
             */
            [
                'key' => 'matches',
                'label' => 'Matches',
                'icon' => 'matches',
                'href' => '/dashboard',
                'visible' => true,
            ],

            [
                'key' => 'profile',
                'label' => 'Profile',
                'icon' => 'profile',
                'href' => '/profile',
                'visible' => $isMember,
            ],
            [
                'key' => 'packages',
                'label' => 'Packages',
                'icon' => 'packages',
                'href' => '/packages',
                'visible' => $isMember,
            ],

            /*
             * ⚠️ "CONFIGURATION", NOT "SETTINGS", AND THE RENAME IS A BUG FIX.
             *
             * A member's own account page is titled Settings. Staff saw two
             * items meaning entirely different things — one holding a phone
             * number and a password, the other holding the payment gateway and
             * the mailbox credentials — under the same word, in the same bar.
             * The client's note was that there were "too much tabs"; the deeper
             * problem was that one of them was named after something else.
             */
            [
                'key' => 'configuration',
                'label' => 'Configuration',
                'icon' => 'shield',
                'href' => '/admin/settings',
                'visible' => $user->can('settings.view'),
            ],

            [
                'key' => 'account',
                'label' => 'Account',
                'icon' => 'account',
                'href' => '/account',
                'visible' => true,
            ],
        ];

        return array_values(array_map(
            fn (array $item) => collect($item)->except('visible')->all(),
            array_filter($items, fn (array $item) => $item['visible']),
        ));
    }

    /**
     * The admin sidebar: every staff area, grouped by the job being done.
     *
     * ⚠️ SEVEN OF THESE HAD NO WAY IN.
     *
     * The top bar carries four items and always has. Meanwhile the admin
     * routes grew to twelve, so trusted contacts, support, reports, payments,
     * emails and credits existed, worked, were permission-gated correctly —
     * and could only be reached by typing the URL. A reviewer could not find
     * the trusted-contact queue that is their job.
     *
     * That is not a visual problem and a prettier tab strip would not have
     * fixed it. Vertical space is what makes room for twelve things, which is
     * the actual reason this is a sidebar rather than a nicer row of tabs.
     *
     * ⚠️ THE SAME RULE AS ABOVE: an item is gated on the permission the ROUTE
     * enforces, never on a role. The permissions here were read off
     * routes/web.php one at a time rather than guessed from the section name —
     * `payments` and `emails` sit behind settings.update, not a payments
     * permission, and guessing would have produced a link that 404s for
     * exactly the person who needs it.
     *
     * @return list<array{key: string, label: string, items: list<array{key: string, label: string, href: string}>}>
     */

    /**
     * Settings that are empty AND that somebody actually has to fill in.
     *
     * ⚠️ EMPTY IS NOT THE SAME AS OUTSTANDING, and counting it that way made
     * the storage screen permanently look unfinished.
     *
     * Photographs are stored on this server, which is the client's decision and
     * the correct one for a platform holding member photographs. The six S3 and
     * R2 credential fields therefore MUST stay empty — filling them would be
     * the mistake, not leaving them. The badge counted all six anyway, so the
     * sidebar showed "6" against a screen that was already correctly
     * configured, and the client reasonably read that as six jobs to do.
     *
     * ⚠️ A BADGE THAT IS WRONG IS WORSE THAN NO BADGE. It is the same failure
     * as an alarm nobody can act on: a number that never reaches zero teaches
     * the person reading it that the number means nothing, and then the WhatsApp
     * and payments counts — which are real, and are waiting on Meta and a bank —
     * stop being read too.
     *
     * ⚠️ EVERYTHING ELSE STILL COUNTS. The AI keys, the WhatsApp credentials and
     * the payment gateway are all genuinely outstanding. This narrows one group
     * that has a driver switch, and does not soften the rest.
     *
     * @return Collection<string, int>
     */
    private static function unfilledButActuallyNeeded(): Collection
    {
        $settings = Setting::query()
            ->whereNull('value')
            ->get(['group', 'key']);

        $driver = Setting::where('group', 'storage')
            ->where('key', 'driver')
            ->value('value');

        /*
         * Only meaningful for a remote driver. Named here rather than derived,
         * because "which fields does S3 need" is a fact about S3 and reading it
         * out of a config file would make it look like a rule of ours.
         */
        $remoteOnly = ['key', 'secret', 'region', 'bucket', 'endpoint', 'url'];

        return $settings
            ->reject(fn ($row) => $row->group === 'storage'
                && $driver === 'local'
                && in_array($row->key, $remoteOnly, true))
            ->groupBy('group')
            ->map(fn ($rows) => $rows->count());
    }

    public static function admin(?User $user): array
    {
        if (! $user) {
            return [];
        }

        /*
         * ⚠️ ASKED ONLY WHEN IT WILL BE SHOWN.
         *
         * This prop is shared with every Inertia response, members included,
         * and a member can never see the configuration section. Counting
         * unfilled settings for them would be a query on every screen of the
         * product to build a list that is then filtered away.
         */
        $outstanding = $user->can('settings.view')
            ? self::unfilledButActuallyNeeded()
            : collect();

        $sections = [
            /*
             * ⚠️ ITS OWN SECTION, ABOVE EVERYTHING, WITH ONE ITEM IN IT.
             *
             * The overview is not a queue and not a setting, and filing it
             * under either would make it the fifth thing in a list of four
             * rather than the place you land. It also has to be first because
             * the wordmark points at whatever the first item is — the logo
             * should go to the overview, not to whichever queue happened to
             * sort highest.
             */
            [
                'key' => 'overview',
                'label' => 'Overview',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'href' => '/admin', 'visible' => $user->can('verifications.view')],
                ],
            ],
            [
                'key' => 'review',
                'label' => 'Review',
                'items' => [
                    ['key' => 'verifications', 'label' => 'Verification queue', 'icon' => 'check', 'href' => '/admin/verifications', 'visible' => $user->can('verifications.view')],
                    ['key' => 'trusted-contacts', 'label' => 'Trusted contacts', 'icon' => 'users', 'href' => '/admin/trusted-contacts', 'visible' => $user->can('verifications.review')],
                    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'flag', 'href' => '/admin/reports', 'visible' => $user->can('verifications.review')],
                    ['key' => 'support', 'label' => 'Support', 'icon' => 'lifebuoy', 'href' => '/admin/support', 'visible' => $user->can('verifications.review')],
                ],
            ],
            [
                'key' => 'operations',
                'label' => 'Operations',
                'items' => [
                    ['key' => 'matches', 'label' => 'Matches', 'icon' => 'matches', 'href' => '/admin/matches', 'visible' => $user->can('matches.view')],
                    ['key' => 'payments', 'label' => 'Payments', 'icon' => 'card', 'href' => '/admin/payments', 'visible' => $user->can('settings.update')],
                    ['key' => 'emails', 'label' => 'Email previews', 'icon' => 'envelope', 'href' => '/admin/emails', 'visible' => $user->can('settings.update')],
                ],
            ],
            [
                'key' => 'configuration',
                'label' => 'Configuration',
                /*
                 * ⚠️ THE EIGHT GROUPS COME FROM THE CONTROLLER THAT SERVES
                 * THEM, not from a second list written out here. Two lists of
                 * the same thing drift, and the drift shows up as a sidebar
                 * item that 404s — which is the defect this whole class exists
                 * to prevent.
                 */
                'items' => collect(SettingsController::groups())
                    ->map(fn (string $label, string $key) => [
                        'key' => "settings.{$key}",
                        'label' => $label,
                        /*
                         * ⚠️ MATCHED BY KEY, WITH A FALLBACK. A group added to
                         * SettingsController that nobody thinks to add here
                         * gets the generic glyph rather than a blank space
                         * where every sibling has an icon — which is what a
                         * missing key looks like, and it reads as broken.
                         */
                        'icon' => match ($key) {
                            'general' => 'sliders',
                            'mail' => 'envelope',
                            'storage' => 'image',
                            'verification' => 'shield',
                            'matching' => 'matches',
                            'packages' => 'packages',
                            'payments' => 'card',
                            'whatsapp' => 'message',
                            default => 'sliders',
                        },
                        'href' => "/admin/settings/{$key}",
                        /*
                         * ⚠️ THE COUNT MOVED HERE FROM THE TAB STRIP RATHER
                         * THAN BEING DROPPED WITH IT.
                         *
                         * It is how the client knows where his attention is
                         * still needed — "this many values are still empty" —
                         * and replacing the tabs without carrying it across
                         * would have quietly removed the one piece of guidance
                         * that screen offered.
                         */
                        'badge' => $outstanding[$key] ?? 0,
                        'visible' => $user->can('settings.view'),
                    ])
                    ->values()
                    ->all(),
            ],

            /*
             * ⚠️ THE STAFF MEMBER'S OWN ACCOUNT, IN THE SIDEBAR WITH
             * EVERYTHING ELSE.
             *
             * It is also in the account menu at the top right, and that is not
             * a duplicate to be tidied away: the menu is where you go to sign
             * out, the sidebar is where you go to change your password. Asking
             * somebody to find their own settings inside an avatar dropdown,
             * on the one screen that has a perfectly good list of destinations
             * down the left, is hiding it for the sake of tidiness.
             *
             * Its own section rather than appended to Configuration, because
             * this is the reviewer's password and phone number, not the
             * platform's mailbox credentials. Those two things being called
             * the same thing is what started this.
             */
            [
                'key' => 'you',
                'label' => 'You',
                'items' => [
                    ['key' => 'account', 'label' => 'Your account', 'icon' => 'account', 'href' => '/account', 'visible' => true],
                ],
            ],
        ];

        return array_values(array_filter(array_map(
            fn (array $section) => [
                'key' => $section['key'],
                'label' => $section['label'],
                'items' => array_values(array_map(
                    fn (array $item) => collect($item)->except('visible')->all(),
                    array_filter($section['items'], fn (array $item) => $item['visible']),
                )),
            ],
            $sections,
            // A section whose every item is hidden must not render as an empty
            // heading. A reviewer sees "Review" and nothing else.
        ), fn (array $section) => $section['items'] !== []));
    }
}
