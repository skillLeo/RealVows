<script setup>
import { computed } from 'vue';

/** Brass conic progress ring with the percentage set in the display face. */
const props = defineProps({
    percent: { type: Number, required: true },
    size: { type: Number, default: 96 },
});

const track = computed(
    () => `conic-gradient(var(--brass) 0 ${props.percent}%, var(--hairline-soft) ${props.percent}% 100%)`
);
const inner = computed(() => `${props.size - 20}px`);
</script>

<template>
    <span
        class="rv-ring"
        :style="{ width: `${size}px`, height: `${size}px`, background: track }"
        role="img"
        :aria-label="`Profile ${percent} percent complete`"
    >
        <span class="rv-ring__inner" :style="{ width: inner, height: inner }">{{ percent }}%</span>
    </span>
</template>

<style scoped>
.rv-ring {
    flex: 0 0 auto;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.rv-ring__inner {
    border-radius: 50%;
    background: var(--card);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--display);
    font-size: var(--fs-body-1);
    line-height: 1;
    color: var(--oxblood);
}
</style>
