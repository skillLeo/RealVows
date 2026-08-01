<script setup>
import { computed, useId } from 'vue';

/**
 * One personality scale: a hairline track, a brass thumb, a word at each end.
 * Stored 0–100, defaulting to the midpoint so an untouched scale reads as
 * "somewhere in between" rather than as an extreme.
 */
const model = defineModel({ type: Number, default: 50 });

const props = defineProps({
    left: { type: String, required: true },
    right: { type: String, required: true },
});

const id = useId();

const leaning = computed(() => {
    if (model.value < 40) return props.left;
    if (model.value > 60) return props.right;
    return 'A bit of both';
});
</script>

<template>
    <div class="rv-scale">
        <div class="rv-scale__ends">
            <span :id="id" class="rv-label">{{ left }} · {{ right }}</span>
            <span class="rv-help">{{ leaning }}</span>
        </div>

        <input
            type="range"
            class="rv-scale__input"
            min="0"
            max="100"
            step="5"
            :value="model"
            :aria-labelledby="id"
            :aria-valuetext="leaning"
            @input="model = Number($event.target.value)"
        />

        <div class="rv-scale__ends">
            <span class="rv-help">{{ left }}</span>
            <span class="rv-help">{{ right }}</span>
        </div>
    </div>
</template>

<style scoped>
.rv-scale {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rv-scale__ends {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
}

.rv-scale__input {
    width: 100%;
    height: var(--tap-min);
    margin: 0;
    background: transparent;
    appearance: none;
    cursor: pointer;
}

.rv-scale__input::-webkit-slider-runnable-track {
    height: 1px;
    background: var(--brass);
}

.rv-scale__input::-moz-range-track {
    height: 1px;
    background: var(--brass);
}

.rv-scale__input::-webkit-slider-thumb {
    appearance: none;
    width: 24px;
    height: 24px;
    margin-top: -12px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: var(--oxblood);
    transition: transform var(--dur-tap) var(--ease);
}

.rv-scale__input::-moz-range-thumb {
    width: 24px;
    height: 24px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: var(--oxblood);
}

.rv-scale__input:active::-webkit-slider-thumb {
    transform: scale(0.94);
}

.rv-scale__input:focus-visible {
    outline: 2px solid var(--brass-bright);
    outline-offset: 2px;
    border-radius: var(--r);
}
</style>
