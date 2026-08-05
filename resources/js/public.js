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

/**
 * ⚠️ THE ONE INTERACTION ON THE MARKETING PAGE.
 *
 * A stranger opens a sealed introduction before being asked to sign up. The
 * ceremony itself is entirely CSS, fired by adding `.rv-unsealing` — the same
 * switch the dashboard throws — so this function only decides WHEN, never
 * WHAT. If it starts describing the animation, the two implementations have
 * begun to drift.
 */
function initIntroDemo() {
    const card = document.querySelector('[data-demo-card]');
    if (!card) return;

    const hit = card.querySelector('[data-demo-open]');
    const arc = card.querySelector('[data-demo-arc]');
    const value = card.querySelector('[data-demo-score-value]');
    const hint = document.querySelector('[data-demo-hint]');
    const after = document.querySelector('[data-demo-after]');
    const again = document.querySelector('[data-demo-reset]');

    const stage = card.querySelector('.rv-demo__stage');
    const score = Number(card.dataset.demoScore);
    if (!hit || !stage || !Number.isFinite(score)) return;

    // 2πr for the ring's r=62. The arc is drawn by winding this offset down.
    const CIRCUMFERENCE = 2 * Math.PI * 62;
    const CEREMONY = 900;

    let timer = null;
    let frame = null;
    let settle = null;
    let reveal = null;

    /**
     * ⚠️ BUILT ON OPEN, NEVER RENDERED SEALED.
     *
     * A sealed card has no image element at all, which is how the real locked
     * cards work — and it is also why no strip of photograph can leak out
     * from under the veil, because there is nothing behind it to leak.
     *
     * Inserted BEFORE the veil so it sits underneath: both are absolutely
     * positioned, so paint order is DOM order.
     */
    const revealPhoto = () => {
        if (stage.querySelector('.rv-demo__photo')) return;

        const src = card.dataset.demoPhoto;
        if (!src) return;

        const picture = document.createElement('picture');

        if (card.dataset.demoPhotoWebp) {
            const source = document.createElement('source');
            source.type = 'image/webp';
            source.srcset = card.dataset.demoPhotoWebp;
            picture.appendChild(source);
        }

        const img = document.createElement('img');
        img.className = 'rv-match__photo rv-demo__photo';
        img.src = src;
        img.alt = '';
        img.width = 840;
        img.height = 630;
        img.decoding = 'async';
        picture.appendChild(img);

        stage.insertBefore(picture, stage.firstChild);
    };

    const hidePhoto = () => {
        const picture = stage.querySelector('.rv-demo__photo')?.closest('picture');
        if (picture) picture.remove();
    };

    /*
     * ⚠️ The markup ships with the ring already drawn and the number already
     * correct, so the card is never wrong for someone whose JS failed. Winding
     * it back is therefore the FIRST thing this does — and only once we know
     * we are able to wind it forward again.
     */
    const wind = () => {
        arc.style.transition = 'none';
        arc.style.strokeDashoffset = String(CIRCUMFERENCE);
        value.textContent = '0';
        // Force a reflow so the reset is not coalesced with the draw that
        // follows it, which would skip the transition entirely.
        void arc.getBoundingClientRect();
        arc.style.transition = '';
    };

    const draw = () => {
        arc.style.strokeDashoffset = String(CIRCUMFERENCE * (1 - score / 100));

        if (REDUCED) {
            value.textContent = String(score);
            return;
        }

        /*
         * ⚠️ THE COUNT IS DECORATION; THIS TIMEOUT IS THE ACTUAL ANSWER.
         *
         * requestAnimationFrame stops in a backgrounded tab. Somebody who
         * taps the card and switches app mid-ceremony comes back to a score
         * frozen at whatever it happened to reach — 43 out of 100 against a
         * real person's introduction, which is worse than never animating it
         * at all. The timeout keeps running, so the number is correct whether
         * or not the animation was ever allowed to finish.
         */
        settle = window.setTimeout(() => { value.textContent = String(score); }, CEREMONY);

        const start = performance.now();
        const step = (now) => {
            // ⚠️ Clamped at BOTH ends. Unclamped below zero the easing
            // inverts and the card briefly showed "-4 out of 100".
            const t = Math.max(0, Math.min((now - start) / CEREMONY, 1));
            if (t >= 1) return;
            value.textContent = String(Math.round(score * (1 - Math.pow(1 - t, 3))));
            frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
    };

    const open = () => {
        window.clearTimeout(timer);
        window.clearTimeout(settle);
        window.clearTimeout(reveal);
        card.classList.add('rv-unsealing', 'is-open');
        hit.hidden = true;
        /*
         * ⚠️ WOUND BACK HERE, NOT ON LOAD.
         *
         * The sealed card shows the score at full strength — that is the
         * whole tease, and it is what the product does. Winding on load left
         * a sealed introduction reading 0 out of 100, which says the opposite
         * of what the section is for.
         */
        revealPhoto();
        wind();
        draw();

        // ⚠️ The line is the point of the section, so it arrives whether or
        // not the ceremony was performed — only the wait changes.
        timer = window.setTimeout(() => {
            if (hint) hint.hidden = true;
            if (after) after.hidden = false;

            // The way back arrives after the sentence, never with it.
            reveal = window.setTimeout(() => {
                if (again) again.hidden = false;
            }, REDUCED ? 0 : 260);
        }, REDUCED ? 200 : CEREMONY);
    };

    const reset = () => {
        window.clearTimeout(timer);
        window.clearTimeout(settle);
        window.clearTimeout(reveal);
        if (frame) cancelAnimationFrame(frame);

        card.classList.remove('rv-unsealing', 'is-open');
        hidePhoto();
        hit.hidden = false;
        if (after) after.hidden = true;
        if (again) again.hidden = true;
        if (hint) hint.hidden = false;

        // Back to the sealed state, which shows the score.
        arc.style.transition = 'none';
        arc.style.strokeDashoffset = String(CIRCUMFERENCE * (1 - score / 100));
        value.textContent = String(score);
        void arc.getBoundingClientRect();
        arc.style.transition = '';

        hit.focus();
    };

    hit.addEventListener('click', open);
    if (again) again.addEventListener('click', reset);
}

/**
 * The menu behind the three lines.
 *
 * ⚠️ A NATIVE <dialog>, AND showModal() RATHER THAN show().
 *
 * showModal() is what makes the browser trap focus, mark the rest of the page
 * inert for assistive technology, and close on Escape. Reimplementing those by
 * hand is where hamburger menus go wrong — the tab key walks out of the open
 * sheet and into the page behind it, and nobody using a mouse ever notices.
 *
 * What is left for us: reflecting state in aria-expanded, closing on a
 * backdrop tap, returning focus to the trigger, and closing on navigation.
 */
function initNav() {
    const toggle = document.querySelector('[data-nav-toggle]');
    const sheet = document.querySelector('[data-nav-sheet]');

    if (!toggle || !sheet) return;

    // Very old browsers without <dialog>. Leave the header links in place and
    // hide a control that would do nothing — a dead button is worse than none.
    if (typeof sheet.showModal !== 'function') {
        toggle.hidden = true;
        return;
    }

    const open = () => {
        sheet.showModal();
        toggle.setAttribute('aria-expanded', 'true');

        /*
         * #8 The Settle is a transition, not a keyframe animation — the motion
         * catalogue is explicit about that and the build enforces it. A
         * transition needs a frame at the starting values before the class
         * lands, or the browser coalesces both into the end state and nothing
         * moves.
         */
        requestAnimationFrame(() => sheet.classList.add('is-open'));
    };

    const close = () => {
        if (sheet.open) sheet.close();
    };

    toggle.addEventListener('click', open);

    sheet.querySelector('[data-nav-close]')?.addEventListener('click', close);

    /*
     * Backdrop tap. The backdrop is not a separate element — a click on it
     * targets the <dialog> itself, so anything landing on the dialog rather
     * than on its contents is outside.
     */
    sheet.addEventListener('click', (event) => {
        if (event.target === sheet) close();
    });

    /*
     * ⚠️ ON ROUTE CHANGE. These are full page loads rather than a client-side
     * router, so "route change" is a link being followed — but the page can be
     * restored from the back/forward cache with the dialog still open, which
     * looks like the menu opened itself.
     */
    window.addEventListener('pagehide', close);

    // Covers Escape and the close button alike, because both fire this.
    sheet.addEventListener('close', () => {
        sheet.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
    });
}

function boot() {
    init();
    initScoreCount();
    initIntroDemo();
    initNav();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
