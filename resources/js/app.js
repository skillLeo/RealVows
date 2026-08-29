import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import reveal from './directives/reveal';
import { startAppBehaviours } from './app-behaviours';
/*
 * ⚠️ BUNDLED, NOT SHARED OVER THE WIRE.
 *
 * The deck was briefly an Inertia shared prop, which put every string in the
 * product into every JSON response — including the word "report", which broke
 * a real privacy guard: a reported member must never learn they were
 * reported, and MatchViewTest asserts that word appears nowhere in their
 * dashboard payload.
 *
 * This is static English text. It belongs in the bundle, where it costs one
 * cached download instead of riding along with every navigation. The PHP file
 * stays the source of truth and CopyDeckTest fails the build if this
 * generated copy drifts from it.
 */
import copy from './copy.generated.json';

const appName = import.meta.env.VITE_APP_NAME || 'RealVows';

/*
 * ── View transitions ──────────────────────────────────────────────────────
 *
 * Runs on the compositor rather than the main thread, which is what makes it
 * feel native on a cheap Android phone rather than merely animated.
 *
 * Guarded twice: browsers without the API navigate normally, and anybody who
 * has asked for reduced motion navigates normally too. Both fall through to
 * exactly the same code path, so an unsupported browser is not a special
 * case to maintain.
 */
function setUpViewTransitions() {
    if (typeof document === 'undefined' || !document.startViewTransition) return;

    let popped = false;

    // Direction is decided before the transition starts and read from CSS,
    // so forward slides left and back slides right without either animation
    // needing to know which page it is on.
    window.addEventListener('popstate', () => {
        popped = true;
    });

    router.on('before', () => {
        document.documentElement.dataset.direction = popped ? 'back' : 'forward';
        popped = false;
    });

    let release = null;

    router.on('start', () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        /*
         * ⚠️ NEVER IN THE ADMIN SHELL, AND THE REASON IS NOT TASTE.
         *
         * The slide is a phone idiom: content moves left going forward and
         * right coming back, matching a stack of screens the thumb pushes
         * through. It reads as native there.
         *
         * On the staff screens the sidebar is permanent and every destination
         * is visible at once. There is no forward and no back — you are simply
         * looking at a different section of the same page — so an 18px lurch
         * on every sidebar click is a twitch, not a transition. The client's
         * words were that it "feels very bad", and he is describing motion
         * that is answering a question nobody asked.
         *
         * Detected from the DOM rather than the URL: AdminLayout renders
         * .rv-admin and nothing else does, so this stays right if the admin
         * ever moves off the /admin prefix, and a member's screens are
         * untouched by it.
         */
        if (document.querySelector('.rv-admin')) return;

        // startViewTransition wants the DOM change inside its callback, but
        // Inertia's swap happens later — so the transition is held open on a
        // promise and released when the new page has actually rendered.
        const transition = document.startViewTransition(
            () => new Promise((resolve) => (release = resolve))
        );

        // If a navigation is cancelled the promise would never settle and
        // the page would stay frozen mid-transition. Release it either way.
        transition.finished.finally(() => (release = null));
    });

    router.on('finish', () => {
        release?.();
        release = null;
    });
}

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    // Code-split per page, so the profile builder's JS never loads on the dashboard.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },

    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', reveal);

        /*
         * ⚠️ THE COPY DECK, REACHABLE FROM EVERY TEMPLATE.
         *
         * lang/en/realvows.php is the single home for every member-facing
         * string. It existed for weeks with 64 keys and ZERO references
         * anywhere in the codebase — complete in a file listing, doing
         * nothing. CopyDeckTest now fails the build on a raw string in a
         * component, and this is what makes complying with it possible.
         *
         * `$t('dashboard.yours')`, and `:count`-style placeholders are
         * replaced from the second argument so pluralisation and counts stay
         * in the deck rather than being assembled in a template.
         */
        app.config.globalProperties.$t = (key, replacements = {}) => {
            const found = key
                .split('.')
                .reduce((carry, part) => (carry == null ? undefined : carry[part]), copy);

            /*
             * ⚠️ A MISSING KEY RETURNS THE KEY, LOUDLY, rather than an empty
             * string. Empty renders as a blank space nobody notices in
             * review; "dashboard.yuors" on screen gets fixed in a minute.
             */
            if (typeof found !== 'string') {
                return key;
            }

            return Object.entries(replacements).reduce(
                (text, [token, value]) => text.replaceAll(`:${token}`, String(value)),
                found
            );
        };

        app.mount(el);

        setUpViewTransitions();
        startAppBehaviours();
    },

    progress: {
        color: '#C5A059',
        showSpinner: false,
    },
});

/*
 * Haptics on navigation. 8ms is below the threshold most people consciously
 * notice and reads as the interface acknowledging a tap rather than buzzing.
 * Touch devices only — a desktop that supports the API should never use it.
 */
/*
 * ⚠️ THE HAPTIC ON EVERY NAVIGATION IS GONE.
 *
 * This fired on router.on('start') — a buzz every time a member opened any
 * page. That is the "constant haptics feel cheap" failure described in the
 * brief, shipped. Haptics now live in resources/js/haptics.js and fire at
 * exactly three moments, and HapticsTest fails the build if navigator.vibrate
 * appears anywhere else.
 */

/*
 * ── Service worker ────────────────────────────────────────────────────────
 *
 * Registered late and deliberately failure-tolerant: a service worker that
 * cannot install must never stop the application loading.
 */
if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Nothing to do. The site works without it; it simply will not
            // open offline.
        });
    });
}
