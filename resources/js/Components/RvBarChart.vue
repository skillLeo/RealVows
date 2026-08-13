<script setup>
import { computed } from 'vue';

/**
 * A bar chart, in SVG, with no charting library behind it.
 *
 * ⚠️ NO DEPENDENCY, AND THAT IS A CHOICE RATHER THAN A LIMITATION.
 *
 * Chart.js is ~70kb gzipped and brings a canvas renderer, its own animation
 * loop and its own colour thinking — none of which agree with a design system
 * that bans shadows and owns three colours. Bars are rectangles. The whole
 * component is smaller than the library's changelog, it inherits the brass and
 * oxblood tokens for free, and it renders as text to a screen reader because
 * the numbers are also in the DOM.
 *
 * ⚠️ IT REFUSES TO DRAW A CHART THAT WOULD LIE.
 *
 * With no data it says so. With every value zero it says so. Both cases render
 * as a flat empty axis otherwise, which reads as a broken chart and sends
 * somebody hunting for a bug that is not there.
 */
const props = defineProps({
    // [{ label: string, count: number }]
    data: { type: Array, required: true },
    // Shown under each bar. Long day labels are turned off on the trend chart.
    showLabels: { type: Boolean, default: true },
    height: { type: Number, default: 160 },
    emptyMessage: { type: String, default: 'Nothing to show yet.' },
});

const max = computed(() => Math.max(...props.data.map((d) => d.count), 0));
const hasSomething = computed(() => props.data.length > 0 && max.value > 0);

/*
 * Geometry in a fixed user space, scaled by the viewBox. Nothing is measured
 * from the DOM, so this cannot cause a layout read and cannot disagree with
 * itself between first paint and hydration.
 */
const W = 100;
const GAP = 1.4;

const bars = computed(() => {
    const n = props.data.length || 1;
    const width = (W - GAP * (n - 1)) / n;

    return props.data.map((d, i) => ({
        ...d,
        x: i * (width + GAP),
        width,
        // A zero-count bar keeps a visible sliver so the day still reads as a
        // day rather than as a hole in the axis.
        heightPct: max.value === 0 ? 0 : Math.max((d.count / max.value) * 100, d.count > 0 ? 4 : 1),
    }));
});
</script>

<template>
    <div class="rv-chart">
        <p v-if="!hasSomething" class="rv-help rv-chart__empty">{{ emptyMessage }}</p>

        <template v-else>
            <svg
                class="rv-chart__svg"
                :viewBox="`0 0 ${W} 100`"
                preserveAspectRatio="none"
                :style="{ height: `${height}px` }"
                role="img"
                :aria-label="`Bar chart. ${data.map((d) => `${d.label}: ${d.count}`).join('. ')}`"
            >
                <rect
                    v-for="bar in bars"
                    :key="bar.label"
                    :x="bar.x"
                    :y="100 - bar.heightPct"
                    :width="bar.width"
                    :height="bar.heightPct"
                    class="rv-chart__bar"
                    :class="{ 'is-empty': bar.count === 0 }"
                    rx="0.6"
                />
            </svg>

            <div v-if="showLabels" class="rv-chart__labels">
                <span v-for="d in data" :key="d.label" class="rv-chart__label">
                    <span class="rv-chart__label-text">{{ d.label }}</span>
                    <strong class="rv-chart__label-value">{{ d.count }}</strong>
                </span>
            </div>
        </template>
    </div>
</template>

<style scoped>
.rv-chart {
    display: grid;
    gap: 12px;
}

.rv-chart__empty {
    padding: 24px 0;
}

/* ⚠️ preserveAspectRatio="none" stretches the user space to the box, which is
   what makes the bars fill the width at any size. It also means stroke widths
   would distort — so nothing here is stroked, only filled. */
.rv-chart__svg {
    width: 100%;
    display: block;
}

.rv-chart__bar {
    fill: var(--oxblood);
}

.rv-chart__bar.is-empty {
    fill: color-mix(in oklab, var(--ink) 12%, transparent);
}

.rv-chart__labels {
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    gap: 4px;
}

.rv-chart__label {
    display: grid;
    gap: 2px;
    text-align: center;
    min-width: 0;
}

.rv-chart__label-text {
    font-size: 11px;
    color: var(--ink-soft);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rv-chart__label-value {
    font-size: 13px;
    font-variant-numeric: tabular-nums;
    color: var(--ink);
}
</style>
