/**
 * v-reveal — fades a section up 16px as it enters the viewport, once, never
 * repeating. One shared IntersectionObserver, no animation library.
 *
 * Honours prefers-reduced-motion by revealing immediately.
 */

let observer = null;

function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function getObserver() {
    if (observer) return observer;

    observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                observer.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.08 }
    );

    return observer;
}

export default {
    mounted(el, binding) {
        el.classList.add('rv-reveal');

        if (prefersReducedMotion()) {
            el.classList.add('is-in');
            return;
        }

        if (binding.value?.delay) {
            el.style.transitionDelay = `${binding.value.delay}ms`;
        }

        getObserver().observe(el);
    },

    unmounted(el) {
        observer?.unobserve(el);
    },
};
