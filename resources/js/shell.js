import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Which chrome a shared screen should wear.
 *
 * ⚠️ /account IS THE ONLY SCREEN BOTH A MEMBER AND STAFF OPEN, and it was the
 * hole in the admin shell. Clicking "Your account" in the sidebar dropped a
 * reviewer into the member layout: the sidebar vanished, a top navigation bar
 * appeared carrying Queue / Matches / Configuration / Account, and the account
 * dropdown listed the same four a third time. Three navigations, one screen.
 *
 * ⚠️ DECIDED FROM WHAT THE PERSON CAN OPEN, NEVER FROM A ROLE.
 *
 * `adminNav` is built server-side by App\Support\Navigation::admin(), gated on
 * the same permissions the routes enforce. Every signed-in person gets a "you"
 * section holding their own account, so its presence proves nothing. A section
 * BESIDES that one means this person has staff areas to navigate, which is
 * exactly the condition for showing them a sidebar.
 *
 * Asking `user.role === 'admin'` would be the obvious version and is the bug
 * App\Support\Navigation was written to stamp out — the nav asking one question
 * while the route asks another, and a dead link living in the gap.
 */
export function useAdminShell() {
    const page = usePage();

    return computed(() =>
        (page.props.adminNav ?? []).some((section) => section.key !== 'you')
    );
}
