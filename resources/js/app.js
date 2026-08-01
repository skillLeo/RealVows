import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import reveal from './directives/reveal';

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
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('reveal', reveal)
            .mount(el);

        setUpViewTransitions();
    },

    progress: {
        color: '#B99154',
        showSpinner: false,
    },
});

/*
 * Haptics on navigation. 8ms is below the threshold most people consciously
 * notice and reads as the interface acknowledging a tap rather than buzzing.
 * Touch devices only — a desktop that supports the API should never use it.
 */
router.on('start', () => {
    if (navigator.vibrate && window.matchMedia('(hover: none)').matches) {
        navigator.vibrate(8);
    }
});

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
