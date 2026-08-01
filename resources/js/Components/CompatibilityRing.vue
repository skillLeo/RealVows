<script setup>
import { computed, onMounted, ref, watch } from 'vue';

/**
 * The Bloom and The Count, together.
 *
 * A brass arc draws from twelve o'clock while the number counts up to meet
 * it. The two are synchronised deliberately — an arc that finishes before
 * its number reads as two separate animations rather than one gesture.
 *
 * The number is the hook and is never diminished, blurred or teased, even on
 * a locked card. It is the whole reason somebody pays.
 */
const props = defineProps({
    score: { type: Number, required: true },
    small: { type: Boolean, default: false },
    label: { type: String, default: 'Compatibility' },

    /**
     * ⚠️ The ceremony runs ONCE.
     *
     * A count-up that replays on every visit is not an arrival, it is a
     * delay — the member already knows the number and is now waiting to read
     * it again. The match view passes `firstView` from the server, derived
     * from whether `viewed_at` has been stamped.
     */
    animate: { type: Boolean, default: true },
});

const RADIUS = 62;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;

const displayed = ref(0);
const offset = ref(CIRCUMFERENCE);

const reduceMotion = () =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function run() {
    const target = Math.max(0, Math.min(100, props.score));

    // With motion off — or on a revisit — the information still arrives. It
    // simply arrives at once rather than being performed.
    if (reduceMotion() || !props.animate) {
        displayed.value = target;
        offset.value = CIRCUMFERENCE * (1 - target / 100);
        return;
    }

    offset.value = CIRCUMFERENCE * (1 - target / 100);

    const duration = 900; // --dur-ceremony
    const started = performance.now();

    const tick = (now) => {
        const elapsed = Math.min(1, (now - started) / duration);

        // Decelerates hard at the end, so the last few numbers land slowly
        // and the total feels arrived at rather than counted to.
        const eased = 1 - Math.pow(1 - elapsed, 3);

        displayed.value = Math.round(target * eased);

        if (elapsed < 1) requestAnimationFrame(tick);
    };

    requestAnimationFrame(tick);
}

onMounted(run);

// Never re-runs on a number that changes often — the score of one match is
// fixed once written, so this only fires when a different match is shown.
watch(() => props.score, run);

const dash = computed(() => `${CIRCUMFERENCE} ${CIRCUMFERENCE}`);
</script>

<template>
    <div class="rv-ring" :class="{ 'rv-ring--sm': small }" role="img" :aria-label="`${label}: ${score} out of 100`">
        <svg class="rv-ring__svg" viewBox="0 0 132 132" aria-hidden="true">
            <circle class="rv-ring__track" cx="66" cy="66" :r="RADIUS" />
            <circle
                class="rv-ring__arc"
                cx="66"
                cy="66"
                :r="RADIUS"
                :stroke-dasharray="dash"
                :stroke-dashoffset="offset"
            />
        </svg>
        <span class="rv-ring__value" aria-hidden="true">{{ displayed }}</span>
    </div>
</template>
