import { router } from '@inertiajs/vue3';

/**
 * The behaviours that decide whether this feels like an application or like a
 * website wearing an app costume.
 *
 * Every one of them is small. Together they are the difference.
 */

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const isTouch = () => window.matchMedia('(hover: none)').matches;

/* ──────────────────────────────────────────────────────────────────────────
   1 · Scroll restoration — the single biggest tell
   ──────────────────────────────────────────────────────────────────────────
   Open a match, press back, land at the top of a list you had scrolled
   halfway down. That one moment tells somebody they are in a browser.

   Position is stored per URL and restored BEFORE paint, so there is no
   visible jump to the top followed by a correction.
────────────────────────────────────────────────────────────────────────── */
function scrollRestoration() {
    const key = (url) => `rv.scroll:${url.split('?')[0]}`;

    const remember = () => {
        try {
            sessionStorage.setItem(key(window.location.pathname), String(window.scrollY));
        } catch { /* private browsing */ }
    };

    // Before leaving, and on every ordinary unload.
    router.on('before', remember);
    window.addEventListener('pagehide', remember);

    let restoring = false;

    router.on('navigate', () => {
        // Only a BACK navigation restores. Going forward to a page should
        // start at the top, which is what a person expects.
        if (!restoring) return;
        restoring = false;

        let y = 0;
        try {
            y = Number(sessionStorage.getItem(key(window.location.pathname)) || 0);
        } catch { /* ignore */ }

        if (!y) return;

        // Before paint, so the top of the list is never briefly visible.
        requestAnimationFrame(() => window.scrollTo(0, y));
    });

    window.addEventListener('popstate', () => {
        restoring = true;
    });
}

/* ──────────────────────────────────────────────────────────────────────────
   2 · Theme colour follows the surface
   ──────────────────────────────────────────────────────────────────────────
   The phone's status bar matching the top of the screen is one of the
   strongest native tells there is, and it costs one meta tag update.
────────────────────────────────────────────────────────────────────────── */
const OXBLOOD_TOP = ['/dashboard', '/matches', '/admin'];

function themeColour() {
    const tag = document.querySelector('meta[name="theme-color"]');
    if (!tag) return;

    const apply = () => {
        const path = window.location.pathname;
        const dark = OXBLOOD_TOP.some((p) => path === p || path.startsWith(`${p}/`));
        tag.setAttribute('content', dark ? '#4A1220' : '#F2EFE9');
    };

    apply();
    router.on('navigate', apply);
}

/* ──────────────────────────────────────────────────────────────────────────
   3 · Toasts — above the tab bar, never over it, never more than one
────────────────────────────────────────────────────────────────────────── */
const queue = [];
let showing = false;

export function toast(message) {
    if (!message) return;
    queue.push(message);
    if (!showing) drain();
}

function drain() {
    const message = queue.shift();

    if (!message) {
        showing = false;
        return;
    }

    showing = true;

    const el = document.createElement('div');
    el.className = 'rv-toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);

    requestAnimationFrame(() => el.classList.add('is-in'));

    let dismissed = false;
    const close = () => {
        if (dismissed) return;
        dismissed = true;
        el.classList.remove('is-in');
        setTimeout(() => {
            el.remove();
            drain();
        }, reduced() ? 0 : 320);
    };

    // Swipe down to dismiss.
    let startY = null;
    el.addEventListener('touchstart', (e) => { startY = e.touches[0].clientY; }, { passive: true });
    el.addEventListener('touchmove', (e) => {
        if (startY !== null && e.touches[0].clientY - startY > 40) close();
    }, { passive: true });

    setTimeout(close, 4000);
}

/**
 * ⚠️ THREE-TIER LOADING: nothing, then a skeleton, then an honest sentence.
 *
 * The tiers exist because the three cases feel completely different and only
 * one of them is a "loading state":
 *
 *  · Under 300ms a spinner is WORSE than nothing. It appears and vanishes as
 *    a flash, which reads as a glitch rather than as speed — the fastest
 *    navigations are the ones that must show nothing at all.
 *  · To 3s a skeleton says "this is arriving, and here is its shape".
 *  · Beyond 3s a skeleton alone starts to look broken, so it is joined by one
 *    plain line admitting it is slow. Never a spinner, never a percentage
 *    nobody can verify, and never "almost there".
 *
 * ⚠️ THE SKELETON COMPONENT EXISTED AND WAS REFERENCED BY NOTHING. Same
 * failure as the copy deck: a thing that looks finished in a file listing and
 * does nothing. This is what actually puts it on screen.
 */
function threeTierLoading() {
    let skeletonTimer = null;
    let slowTimer = null;
    let el = null;

    const clear = () => {
        clearTimeout(skeletonTimer);
        clearTimeout(slowTimer);
        skeletonTimer = slowTimer = null;
        if (el) { el.remove(); el = null; }
    };

    router.on('start', () => {
        clear();

        // Tier 1: under 300ms, deliberately nothing.
        skeletonTimer = setTimeout(() => {
            // Tier 2: the shape of what is coming.
            el = document.createElement('div');
            el.className = 'rv-loading';
            el.setAttribute('role', 'status');
            el.setAttribute('aria-live', 'polite');
            el.innerHTML =
                '<span class="rv-skeleton rv-loading__bar"></span>' +
                '<span class="rv-skeleton rv-loading__bar rv-loading__bar--short"></span>';
            document.body.appendChild(el);

            // Tier 3: past 3s, say so in words.
            slowTimer = setTimeout(() => {
                if (!el) return;
                const line = document.createElement('p');
                line.className = 'rv-loading__line';
                line.textContent = 'This is taking longer than usual. Still working.';
                el.appendChild(line);
            }, 2700);
        }, 300);
    });

    router.on('finish', clear);
}

/** Flash messages arrive as Inertia props; surface them as toasts. */
function flashToasts() {
    router.on('finish', () => {
        const flash = router.page?.props?.flash;
        if (flash?.success) toast(flash.success);
    });
}

/* ──────────────────────────────────────────────────────────────────────────
   4 · The top bar collapses on scroll down and returns on scroll up
   Transform only, so it never re-renders.
────────────────────────────────────────────────────────────────────────── */
function collapsingTopBar() {
    const bar = document.querySelector('.rv-app__bar');
    if (!bar || reduced()) return;

    let last = window.scrollY;
    let ticking = false;

    const onScroll = () => {
        if (ticking) return;
        ticking = true;

        requestAnimationFrame(() => {
            const y = window.scrollY;

            // Never hide near the top — a bar that vanishes on the first
            // flick reads as a glitch rather than a behaviour.
            if (y > 120 && y > last + 4) {
                bar.classList.add('is-collapsed');
            } else if (y < last - 4) {
                bar.classList.remove('is-collapsed');
            }

            last = y;
            ticking = false;
        });
    };

    window.addEventListener('scroll', onScroll, { passive: true });
}

/* ──────────────────────────────────────────────────────────────────────────
   5 · Pull to refresh — a brass ring that draws itself as you pull
────────────────────────────────────────────────────────────────────────── */
const THRESHOLD = 72;

function pullToRefresh() {
    if (!isTouch()) return;

    const host = document.querySelector('[data-pull-refresh]');
    if (!host) return;

    // Built with DOM methods rather than innerHTML. Nothing here is user
    // input, but a codebase where innerHTML is never reached for is one where
    // it cannot be reached for carelessly later.
    const ring = document.createElement('div');
    ring.className = 'rv-pull';
    ring.appendChild(Object.assign(document.createElement('span'), { className: 'rv-pull__ring' }));
    host.prepend(ring);

    let startY = null;
    let pulled = 0;

    host.addEventListener('touchstart', (e) => {
        // Only from the very top, or this fights ordinary scrolling.
        startY = window.scrollY === 0 ? e.touches[0].clientY : null;
    }, { passive: true });

    host.addEventListener('touchmove', (e) => {
        if (startY === null) return;

        pulled = Math.max(0, e.touches[0].clientY - startY);
        const progress = Math.min(1, pulled / THRESHOLD);

        ring.style.setProperty('--pull', String(progress));
        ring.style.transform = `translateY(${Math.min(pulled * 0.5, 56)}px)`;
    }, { passive: true });

    host.addEventListener('touchend', () => {
        if (pulled >= THRESHOLD) {
            router.reload({ preserveScroll: false });
        }

        ring.style.transform = '';
        ring.style.setProperty('--pull', '0');
        startY = null;
        pulled = 0;
    });
}

/* ──────────────────────────────────────────────────────────────────────────
   6 · Long press opens the actions that would otherwise need a menu
────────────────────────────────────────────────────────────────────────── */
function longPress() {
    if (!isTouch()) return;

    let timer = null;
    let origin = null;

    document.addEventListener('touchstart', (e) => {
        const card = e.target.closest('[data-longpress]');
        if (!card) return;

        origin = e.touches[0];
        timer = setTimeout(() => {
            // ⚠️ No haptic here. A long-press is a gesture, not an event.
            card.dispatchEvent(new CustomEvent('rv:longpress', { bubbles: true }));
        }, 500);
    }, { passive: true });

    const cancel = (e) => {
        // Any real movement is a scroll, not a press.
        if (timer && origin && e.touches?.[0]) {
            const moved = Math.hypot(
                e.touches[0].clientX - origin.clientX,
                e.touches[0].clientY - origin.clientY
            );
            if (moved < 10) return;
        }

        clearTimeout(timer);
        timer = null;
    };

    document.addEventListener('touchmove', cancel, { passive: true });
    document.addEventListener('touchend', cancel, { passive: true });
    document.addEventListener('touchcancel', cancel, { passive: true });
}

export function startAppBehaviours() {
    scrollRestoration();
    threeTierLoading();
    themeColour();
    flashToasts();
    collapsingTopBar();
    pullToRefresh();
    longPress();
}
