<?php

namespace App\Support;

use App\Models\User;

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

            [
                'key' => 'settings',
                'label' => 'Settings',
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
}
