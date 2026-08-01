<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

/**
 * The photographs on a match view.
 *
 * ⚠️ NOT a carousel of equals. Photo one is the portrait and it opens at
 * full width with the identity overlaid on it. Photos two to four are the
 * rail beneath. The face arrives first and alone, which is how you look at a
 * person rather than at a gallery.
 *
 * ⚠️ NO SWIPE-TO-DECIDE, in any direction, ever. Horizontal movement here
 * scrolls the rail and nothing else. That gesture is the visual grammar of
 * casual dating apps and this product is defined against them.
 */
const props = defineProps({
    photos: { type: Array, default: () => [] },
    name: { type: String, default: '' },
});

const portrait = computed(() => props.photos[0] ?? null);
const rest = computed(() => props.photos.slice(1));

// ── Full-screen viewer ───────────────────────────────────────────────────
const viewer = ref(null);
const active = ref(0);

/**
 * Opening pushes a history entry so the hardware and gesture back button
 * closes the viewer instead of navigating away — the back-button contract.
 */
function open(index) {
    active.value = index;
    viewer.value?.showModal();
    history.pushState({ rvViewer: true }, '');
    window.addEventListener('popstate', closeFromHistory);
}

function closeFromHistory() {
    viewer.value?.close();
    window.removeEventListener('popstate', closeFromHistory);
}

function close() {
    viewer.value?.close();

    // Consume our own history entry so back does not have to be pressed twice.
    if (history.state?.rvViewer) history.back();
    window.removeEventListener('popstate', closeFromHistory);
}

onBeforeUnmount(() => window.removeEventListener('popstate', closeFromHistory));

function onRailScroll(event) {
    const el = event.target;
    active.value = Math.round(el.scrollLeft / el.clientWidth);
}
</script>

<template>
    <div class="rv-gal">
        <!-- The portrait, with the identity block overlaid. -->
        <div class="rv-gal__portrait">
            <img
                v-if="portrait"
                :src="portrait.url"
                :alt="name"
                class="rv-gal__img"
                fetchpriority="high"
                decoding="async"
                @click="open(0)"
            />
            <div v-else class="rv-gal__img rv-gal__img--none" aria-hidden="true" />

            <div class="rv-gal__overlay">
                <slot name="overlay" />
            </div>
        </div>

        <!-- The rest, as a snap rail. -->
        <div v-if="rest.length" class="rv-gal__rail" @scroll.passive="onRailScroll">
            <button
                v-for="(photo, i) in rest"
                :key="photo.slot"
                type="button"
                class="rv-gal__thumb rv-pressable"
                :aria-label="`Photo ${i + 2} of ${photos.length}`"
                @click="open(i + 1)"
            >
                <img :src="photo.url" :alt="name" loading="lazy" decoding="async" />
            </button>
        </div>

        <div v-if="rest.length" class="rv-gal__dots" aria-hidden="true">
            <span
                v-for="(photo, i) in rest"
                :key="photo.slot"
                class="rv-gal__dot"
                :class="{ 'is-active': active === i }"
            />
        </div>

        <!--
            Native <dialog>, so the browser owns focus trapping, inertness and
            the escape key rather than us reimplementing all three badly.
        -->
        <dialog ref="viewer" class="rv-viewer" @close="active = 0" @click.self="close">
            <button type="button" class="rv-viewer__close" aria-label="Close" @click="close">✕</button>
            <img v-if="photos[active]" :src="photos[active].url" :alt="name" class="rv-viewer__img" />
        </dialog>
    </div>
</template>

<style scoped>
.rv-gal {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rv-gal__portrait {
    position: relative;
    overflow: hidden;
}

@media (min-width: 768px) {
    .rv-gal__portrait {
        border: 1px solid var(--hairline);
        border-radius: var(--r);
    }
}

.rv-gal__img {
    display: block;
    width: 100%;
    aspect-ratio: 3 / 4;
    object-fit: cover;
    background: var(--cream);
    cursor: zoom-in;
}

.rv-gal__img--none {
    background: linear-gradient(160deg, var(--oxblood) 0%, var(--oxblood-deep) 100%);
    cursor: default;
}

.rv-gal__overlay {
    position: absolute;
    inset: auto 0 0 0;
    pointer-events: none;
}

.rv-gal__rail {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 33%;
    gap: 8px;
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    overscroll-behavior-x: contain;
    scrollbar-width: none;
    padding-inline: 20px;
}

.rv-gal__rail::-webkit-scrollbar { display: none; }

@media (min-width: 768px) {
    .rv-gal__rail { padding-inline: 0; }
}

.rv-gal__thumb {
    scroll-snap-align: start;
    padding: 0;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: none;
    overflow: hidden;
    cursor: zoom-in;
    transition: transform var(--dur-tap) var(--ease);
}

.rv-gal__thumb img {
    display: block;
    width: 100%;
    aspect-ratio: 3 / 4;
    object-fit: cover;
}

.rv-gal__dots {
    display: flex;
    justify-content: center;
    gap: 6px;
}

.rv-gal__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--hairline);
    transition: width var(--dur-quick) var(--ease), background var(--dur-quick) var(--ease);
}

.rv-gal__dot.is-active {
    width: 8px;
    height: 8px;
    background: var(--brass);
}

/* ── The viewer ───────────────────────────────────────────────────────── */

.rv-viewer {
    width: 100vw;
    max-width: 100vw;
    height: 100dvh;
    max-height: 100dvh;
    margin: 0;
    padding: 0;
    border: 0;
    background: var(--oxblood-deep);
    display: grid;
    place-items: center;
}

.rv-viewer::backdrop { background: rgba(31, 21, 24, 0.9); }

.rv-viewer__img {
    max-width: 100%;
    max-height: 100dvh;
    object-fit: contain;
}

.rv-viewer__close {
    position: absolute;
    top: calc(16px + env(safe-area-inset-top));
    right: 16px;
    width: var(--tap-min);
    height: var(--tap-min);
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: rgba(74, 18, 32, 0.7);
    color: var(--cream);
    font-size: 18px;
    cursor: pointer;
}
</style>
