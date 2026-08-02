{{--
    ── The hero arrival ──────────────────────────────────────────────────────

    The page should feel like it is being SET, the way a table is set:
    wordmark, eyebrow, headline word by word, the brass rule drawing, then
    the photograph fading up. Roughly 1.6 seconds.

    ⚠️ ONCE PER SESSION. An arrival that happens every single visit is not an
    arrival, it is a delay — and the second time somebody sees it they are
    waiting rather than watching. `sessionStorage` rather than `localStorage`
    so a genuinely new visit still gets it.

    Inlined rather than bundled because the home page is plain Blade carrying
    almost no JavaScript, and one small script keeps it that way. No Vue is
    mounted here.
--}}
<script>
(function () {
    var root = document.documentElement;
    var hero = document.querySelector('[data-hero]');

    if (!hero) return;

    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var seen = false;

    try {
        seen = sessionStorage.getItem('rv.arrival') === '1';
    } catch (e) {
        /* Private browsing can throw on sessionStorage. Treat it as seen —
           showing the sequence is the optional half, and a thrown error must
           never stop the page rendering. */
        seen = true;
    }

    /* Anyone who has asked for less motion, or has already watched it, gets
       the finished state immediately. Nothing is lost but the performance. */
    if (reduced || seen) {
        root.setAttribute('data-arrival', 'done');
        return;
    }

    /* Split the headline into words so each can arrive in turn. Done in JS
       rather than in the template so the markup stays one readable sentence
       for search engines and screen readers. */
    var heading = hero.querySelector('[data-arrival-words]');

    if (heading) {
        var text = heading.textContent.trim();
        heading.textContent = '';

        text.split(/(\s+)/).forEach(function (part, i) {
            if (!part.trim()) {
                heading.appendChild(document.createTextNode(part));
                return;
            }

            var span = document.createElement('span');
            span.className = 'rv-word';
            span.textContent = part;
            // 90ms between words, after the eyebrow has landed.
            span.style.animationDelay = (260 + (i / 2) * 90) + 'ms';
            heading.appendChild(span);
        });
    }

    // Stagger the non-headline steps.
    hero.querySelectorAll('.rv-arrive-step').forEach(function (el, i) {
        el.style.animationDelay = (i * 140) + 'ms';
    });

    root.setAttribute('data-arrival', 'pending');

    requestAnimationFrame(function () {
        root.setAttribute('data-arrival', 'running');
    });

    try {
        sessionStorage.setItem('rv.arrival', '1');
    } catch (e) { /* nothing to do */ }
})();
</script>
