/**
 * ⚠️ THE ONLY PLACE IN THE PRODUCT PERMITTED TO CALL navigator.vibrate.
 *
 * Haptics were fired from three places and none of them was one of the three
 * moments that earn it. The worst was `router.on('start')` — a buzz on EVERY
 * page navigation, which is exactly the "constant haptics feel cheap" failure,
 * live. The others were the pull-to-refresh threshold and a long-press.
 *
 * A haptic is the product touching somebody. It should happen when something
 * has actually happened to them, and three times in a whole membership is the
 * right order of magnitude:
 *
 *   1. UNSEAL          — an introduction opening, the moment they paid for
 *   2. GUARDIAN_APPROVED — their trusted contact has cleared an introduction
 *   3. MUTUAL_INTEREST  — the other person said yes too
 *
 * All three are things a member would tell somebody about. Navigating to a
 * settings page is not.
 *
 * ⚠️ HapticsTest ASSERTS navigator.vibrate APPEARS IN NO OTHER FILE. Adding a
 * fourth call site anywhere else fails the build, because the discipline here
 * is not "use it sparingly" — it is a closed list.
 */

const MOMENTS = {
    unseal: 12,
    guardianApproved: 10,
    mutualInterest: [10, 40, 10],
};

/**
 * ⚠️ TOUCH DEVICES ONLY, AND SILENT WHERE UNSUPPORTED.
 *
 * A laptop with a trackpad reports the API and should never use it. `hover:
 * none` is the honest test for "this is held in a hand".
 */
function canVibrate() {
    return typeof navigator !== 'undefined'
        && typeof navigator.vibrate === 'function'
        && window.matchMedia('(hover: none)').matches
        && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * @param {'unseal'|'guardianApproved'|'mutualInterest'} moment
 */
export function haptic(moment) {
    const pattern = MOMENTS[moment];

    // An unknown moment is a silent no-op rather than a default buzz. A typo
    // must not become a new haptic nobody decided on.
    if (!pattern || !canVibrate()) return;

    navigator.vibrate(pattern);
}

export const HAPTIC_MOMENTS = Object.keys(MOMENTS);
