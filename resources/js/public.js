/**
 * The entire JavaScript payload for the public marketing pages.
 *
 * Home and the legal pages are plain Blade — no Vue, no Inertia, no
 * hydration. This file is the "tiny sprinkle" that carries the motion, and
 * it must stay small: the home page has a sub-30KB JS budget and members
 * open it on mid-range phones over 4G. A slow page is an unromantic page.
 *
 * Two behaviours, one observer:
 *   [data-reveal]  — fades the section up 16px as it enters, once
 *   [data-stagger] — delays each child by 80ms so siblings arrive in sequence
 */

const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function revealEverythingImmediately(nodes) {
    nodes.forEach((node) => node.classList.add('is-in'));
}

function init() {
    const nodes = Array.from(document.querySelectorAll('[data-reveal]'));
    if (!nodes.length) return;

    if (REDUCED || !('IntersectionObserver' in window)) {
        revealEverythingImmediately(nodes);
        return;
    }

    // Stagger siblings within a group by 80ms.
    document.querySelectorAll('[data-stagger]').forEach((group) => {
        Array.from(group.children).forEach((child, index) => {
            if (child.hasAttribute('data-reveal')) {
                child.style.transitionDelay = `${index * 80}ms`;
            }
        });
    });

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.08 }
    );

    nodes.forEach((node) => observer.observe(node));
}

/**
 * The compatibility score counts up from 0 over 800ms the first time it
 * appears. The one flourish worth having — it makes the number feel earned.
 */
function initScoreCount() {
    const el = document.querySelector('[data-count-to]');
    if (!el) return;

    const target = Number(el.dataset.countTo);
    if (!Number.isFinite(target)) return;

    if (REDUCED || !('IntersectionObserver' in window)) {
        el.textContent = String(target);
        return;
    }

    el.textContent = '0';

    const observer = new IntersectionObserver(
        (entries) => {
            if (!entries[0].isIntersecting) return;
            observer.disconnect();

            const start = performance.now();
            const step = (now) => {
                const t = Math.min((now - start) / 800, 1);
                el.textContent = String(Math.round(target * (1 - Math.pow(1 - t, 3))));
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
        },
        { threshold: 0.4 }
    );

    observer.observe(el);
}

function boot() {
    init();
    initScoreCount();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
