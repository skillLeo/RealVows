<script setup>
import { computed } from 'vue';

/**
 * A donut, drawn with one circle and stroke-dasharray.
 *
 * ⚠️ NO ARC PATHS, NO TRIGONOMETRY, NO LIBRARY.
 *
 * The usual way to build this is an arc path per segment, which needs sin/cos,
 * a large-arc flag, and gets the 100%-of-one-category case wrong (the start and
 * end points coincide, so the arc collapses and the segment vanishes — a pie
 * showing nothing when one category holds everything).
 *
 * Stacking dashed circles avoids all of it. Each segment is the same circle
 * with a dash the length of its share and an offset placing it after the
 * previous one. A single 100% segment is a complete ring, which is correct and
 * needs no special case.
 *
 * ⚠️ THE NUMBERS ARE IN THE LEGEND, NOT ONLY IN THE ANGLES. A ring is a poor
 * instrument for reading a quantity, and nobody should have to estimate.
 */
const props = defineProps({
    // [{ label: string, count: number }]
    data: { type: Array, required: true },
    size: { type: Number, default: 148 },
    emptyMessage: { type: String, default: 'Nothing to show yet.' },
});

// r chosen so the circumference is very close to 100, which lets every dash be
// written directly as a percentage.
const R = 15.915;
const CIRCUMFERENCE = 100;

const total = computed(() => props.data.reduce((sum, d) => sum + d.count, 0));

const segments = computed(() => {
    if (total.value === 0) return [];

    let offset = 0;

    return props.data
        .filter((d) => d.count > 0)
        .map((d, i) => {
            const share = (d.count / total.value) * 100;
            const segment = {
                ...d,
                share,
                dash: `${share} ${CIRCUMFERENCE - share}`,
                // Negative offset advances clockwise from twelve o'clock.
                offset: -offset,
                index: i,
            };

            offset += share;

            return segment;
        });
});
</script>

<template>
    <div class="rv-donut">
        <p v-if="total === 0" class="rv-help">{{ emptyMessage }}</p>

        <template v-else>
            <svg
                :width="size"
                :height="size"
                viewBox="0 0 42 42"
                class="rv-donut__svg"
                role="img"
                :aria-label="`${data.map((d) => `${d.label}: ${d.count}`).join('. ')}`"
            >
                <!-- The track, so a single small segment still reads as part of
                     a whole rather than as a floating arc. -->
                <circle class="rv-donut__track" cx="21" cy="21" :r="R" fill="none" />

                <circle
                    v-for="segment in segments"
                    :key="segment.label"
                    class="rv-donut__segment"
                    :class="`rv-donut__segment--${segment.index % 3}`"
                    cx="21"
                    cy="21"
                    :r="R"
                    fill="none"
                    :stroke-dasharray="segment.dash"
                    :stroke-dashoffset="segment.offset"
                />

                <text x="21" y="21" class="rv-donut__total">{{ total }}</text>
            </svg>

            <ul class="rv-donut__legend">
                <li v-for="segment in segments" :key="segment.label">
                    <span class="rv-donut__swatch" :class="`rv-donut__segment--${segment.index % 3}`" />
                    <span class="rv-donut__label">{{ segment.label }}</span>
                    <strong class="rv-donut__value">{{ segment.count }}</strong>
                    <span class="rv-donut__share">{{ Math.round(segment.share) }}%</span>
                </li>
            </ul>
        </template>
    </div>
</template>

<style scoped>
.rv-donut {
    display: flex;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

/* Rotated so the first segment starts at twelve o'clock, which is where every
   reader assumes a ring begins. */
.rv-donut__svg {
    transform: rotate(-90deg);
    flex: none;
}

.rv-donut__track {
    stroke: color-mix(in oklab, var(--ink) 8%, transparent);
    stroke-width: 4;
}

.rv-donut__segment {
    stroke-width: 4;
    /* Butt, not round: rounded caps overlap the neighbouring segment and make
       a 2% slice look like a 5% one. */
    stroke-linecap: butt;
}

.rv-donut__segment--0 { stroke: var(--oxblood); }
.rv-donut__segment--1 { stroke: var(--brass); }
.rv-donut__segment--2 { stroke: var(--rose); }

.rv-donut__total {
    transform: rotate(90deg);
    transform-origin: 21px 21px;
    text-anchor: middle;
    dominant-baseline: central;
    font-size: 9px;
    font-weight: 600;
    fill: var(--ink);
}

.rv-donut__legend {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 8px;
    min-width: 0;
}

.rv-donut__legend li {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.rv-donut__swatch {
    width: 10px;
    height: 10px;
    border-radius: 2px;
    flex: none;
    /* The swatch reuses the segment classes, which set stroke rather than
       background, so the colour is taken from the same declaration. */
    background: currentColor;
    color: inherit;
}

.rv-donut__swatch.rv-donut__segment--0 { background: var(--oxblood); }
.rv-donut__swatch.rv-donut__segment--1 { background: var(--brass); }
.rv-donut__swatch.rv-donut__segment--2 { background: var(--rose); }

.rv-donut__label { color: var(--ink-soft); }

.rv-donut__value {
    margin-inline-start: auto;
    font-variant-numeric: tabular-nums;
}

.rv-donut__share {
    font-size: 12px;
    color: var(--ink-soft);
    font-variant-numeric: tabular-nums;
    min-width: 34px;
    text-align: end;
}
</style>
