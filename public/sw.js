/*
 * RealVows service worker.
 *
 * ⚠️ THE RULE THAT MATTERS MORE THAN OFFLINE SUPPORT:
 *
 * This must NEVER cache an authenticated response or a signed photo URL.
 *
 * A cached member photograph would sit on the device after sign-out, and a
 * cached dashboard would show one member's introductions to whoever picks up
 * the phone next. On a marriage platform holding verified photographs of real
 * people, that is a far worse failure than "the page did not load offline".
 *
 * So the allow-list is deliberately narrow: the shell, the fonts, the icons
 * and the offline page. Everything else goes to the network, every time.
 */

const VERSION = 'rv-v2';
const SHELL = `${VERSION}-shell`;

/* Only things that are identical for every visitor and contain nobody's data. */
const PRECACHE = [
    '/offline',
    '/icons/rv-192.png',
    '/icons/rv-512.png',
    '/icons/rv-maskable-512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL)
            .then((cache) => cache.addAll(PRECACHE))
            // A precache miss must not block installation — a missing icon is
            // not a reason to have no service worker at all.
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(keys.filter((key) => !key.startsWith(VERSION)).map((key) => caches.delete(key)))
            )
            .then(() => self.clients.claim())
    );
});

/** Paths that must never be cached under any circumstances. */
function isPrivate(url) {
    return (
        url.pathname.startsWith('/photos/') ||
        url.pathname.startsWith('/verification/') ||
        url.pathname.startsWith('/admin/') ||
        url.pathname.startsWith('/checkout/') ||
        url.pathname.startsWith('/payments/') ||
        url.pathname.startsWith('/matches/') ||
        url.pathname === '/dashboard' ||
        url.pathname === '/account' ||
        url.pathname === '/purchases' ||
        url.searchParams.has('signature')
    );
}

/** Build assets are content-hashed, so caching them is safe and permanent. */
function isImmutableAsset(url) {
    return url.pathname.startsWith('/build/assets/') || url.pathname.startsWith('/icons/');
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') return;

    const url = new URL(request.url);

    // Third-party requests are none of our business.
    if (url.origin !== self.location.origin) return;

    // ⚠️ Anything private bypasses the cache entirely — not "network first",
    // not "stale while revalidate". It never enters the cache at all.
    if (isPrivate(url)) return;

    if (isImmutableAsset(url)) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(SHELL).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    })
            )
        );

        return;
    }

    // Public pages: network first, with a designed offline page as the
    // fallback rather than the browser's error screen.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline').then((hit) => hit || Response.error()))
        );
    }
});
