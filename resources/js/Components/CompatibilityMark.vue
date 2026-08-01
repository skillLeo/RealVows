<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The compatibility mark — the product's signature visual.
 *
 * ⚠️ Rebuilt from the approved foundation with the two sign lozenges and
 * their caption removed. The mechanism behind the score stays exactly as
 * signed (it is 40% of the weighting) but it is internal only and must never
 * be named, drawn or hinted at anywhere a user can see — the reasoning lives
 * in app/Services/Sign/SignCalculator.php. What survives from the original
 * mark is what mattered visually: the score large in the display face, the
 * brass rule, and the three sizes (96 / 56 / 32 contexts).
 *
 * The score counts up from 0 over 800ms the first time it enters the
 * viewport. It is the one flourish worth having — it makes the number feel
 * earned. Disabled under prefers-reduced-motion.
 */
const props = defineProps({
    score: { type: Number, required: true },
    size: { type: String, default: 'md', validator: (v) => ['lg', 'md', 'sm'].includes(v) },
    tone: { type: String, default: 'light', validator: (v) => ['light', 'dark'].includes(v) },
    caption: { type: String, default: '' },
    animate: { type: Boolean, default: true },
});

const root = ref(null);
const shown = ref(props.animate ? 0 : props.score);
let observer = null;
let frame = null;

function countUp() {
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced || !props.animate) {
        shown.value = props.score;
        return;
    }

    const duration = 800;
    const start = performance.now();

    const step = (now) => {
        const t = Math.min((now - start) / duration, 1);
        // Same soft settle as the rest of the system's motion.
        const eased = 1 - Math.pow(1 - t, 3);
        shown.value = Math.round(props.score * eased);
        if (t < 1) frame = requestAnimationFrame(step);
    };

    frame = requestAnimationFrame(step);
}

onMounted(() => {
    if (!props.animate) return;

    observer = new IntersectionObserver(
        (entries) => {
            if (!entries[0].isIntersecting) return;
            observer.disconnect();
            countUp();
        },
        { threshold: 0.4 }
    );

    observer.observe(root.value);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    if (frame) cancelAnimationFrame(frame);
});
</script>

<template>
    <div ref="root" class="rv-mark" :class="[`rv-mark--${size}`, `rv-mark--${tone}`]">
        <span v-if="size === 'lg'" class="rv-label rv-label--brass">Compatibility score</span>

        <!-- Large: score above, rule beneath carrying the label between two hairlines -->
        <template v-if="size === 'lg'">
            <span class="rv-mark__score">
                {{ shown }}<span class="rv-visually-hidden"> out of 100</span>
            </span>
            <span class="rv-mark__rule-row">
                <span class="rv-mark__rule" />
                <span class="rv-mark__rule-label">Out of 100</span>
                <span class="rv-mark__rule" />
            </span>
        </template>

        <!-- Medium: score centred, the rule alone carries the join -->
        <template v-else-if="size === 'md'">
            <span class="rv-mark__score">
                {{ shown }}<span class="rv-visually-hidden"> out of 100</span>
            </span>
            <span class="rv-mark__rule" />
        </template>

        <!-- Small: single line, minimum permitted size -->
        <template v-else>
            <span class="rv-mark__rule" />
            <span class="rv-mark__score">
                {{ shown }}<span class="rv-visually-hidden"> out of 100</span>
            </span>
            <span class="rv-mark__rule" />
        </template>

        <p v-if="caption" class="rv-mark__caption">{{ caption }}</p>
    </div>
</template>
