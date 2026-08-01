<script setup>
import { computed } from 'vue';

/**
 * Pick five values, in order. The order carries meaning — first is what
 * matters most — so the chosen list is shown separately from the pool, with
 * its rank in brass numerals and controls to move an entry up or down.
 *
 * Ordering is by button rather than drag: it works with a keyboard, works
 * with a screen reader, and works with a thumb on a moving bus, which drag
 * does not.
 */
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    options: { type: Array, required: true },
    max: { type: Number, default: 5 },
});

const labelFor = (value) => props.options.find((option) => option.value === value)?.label ?? value;

const available = computed(() => props.options.filter((option) => !model.value.includes(option.value)));
const isFull = computed(() => model.value.length >= props.max);

function add(value) {
    if (isFull.value) return;
    model.value = [...model.value, value];
}

function remove(value) {
    model.value = model.value.filter((entry) => entry !== value);
}

function move(index, offset) {
    const target = index + offset;
    if (target < 0 || target >= model.value.length) return;

    const next = [...model.value];
    [next[index], next[target]] = [next[target], next[index]];
    model.value = next;
}
</script>

<template>
    <div class="rv-stack" style="gap: 20px">
        <div class="rv-field__head">
            <span class="rv-label">Your five core values, most important first</span>
            <span class="rv-help">{{ model.length }} of {{ max }}</span>
        </div>

        <ol v-if="model.length" class="rv-ranker">
            <li v-for="(value, index) in model" :key="value" class="rv-ranker__row">
                <span class="rv-ranker__rank" aria-hidden="true">{{ index + 1 }}</span>
                <span class="rv-ranker__name">{{ labelFor(value) }}</span>

                <span class="rv-ranker__controls">
                    <button
                        type="button"
                        :disabled="index === 0"
                        :aria-label="`Move ${labelFor(value)} up`"
                        @click="move(index, -1)"
                    >
                        ↑
                    </button>
                    <button
                        type="button"
                        :disabled="index === model.length - 1"
                        :aria-label="`Move ${labelFor(value)} down`"
                        @click="move(index, 1)"
                    >
                        ↓
                    </button>
                    <button type="button" :aria-label="`Remove ${labelFor(value)}`" @click="remove(value)">
                        ×
                    </button>
                </span>
            </li>
        </ol>

        <p v-else class="rv-help">Choose up to {{ max }} from below.</p>

        <div v-if="!isFull" class="rv-stack" style="gap: 12px">
            <span class="rv-label">Choose from</span>
            <ul class="rv-ranker__pool">
                <li v-for="option in available" :key="option.value">
                    <button type="button" class="rv-ranker__chip" @click="add(option.value)">
                        {{ option.label }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.rv-ranker {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rv-ranker__row {
    min-height: 56px;
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 0 12px 0 16px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
}

.rv-ranker__rank {
    flex: 0 0 auto;
    font-family: var(--display);
    font-size: var(--fs-body-1);
    line-height: 1;
    color: var(--brass);
}

.rv-ranker__name {
    flex: 1 1 auto;
    font-size: var(--fs-body-2);
}

.rv-ranker__controls {
    display: flex;
    gap: 4px;
}

.rv-ranker__controls button {
    width: var(--tap-min);
    height: var(--tap-min);
    border: 1px solid transparent;
    border-radius: var(--r);
    background: transparent;
    color: var(--ink-soft);
    font-size: var(--fs-body-2);
    cursor: pointer;
    transition:
        background var(--dur-tap) var(--ease),
        color var(--dur-tap) var(--ease);
}

.rv-ranker__controls button:hover:not(:disabled) {
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
    color: var(--oxblood);
}

.rv-ranker__controls button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

.rv-ranker__pool {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rv-ranker__chip {
    min-height: var(--tap-min);
    padding: 0 16px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: transparent;
    color: var(--ink);
    font-size: var(--fs-body-2);
    cursor: pointer;
    transition:
        background var(--dur-tap) var(--ease),
        border-color var(--dur-tap) var(--ease);
}

.rv-ranker__chip:hover {
    border-color: var(--brass-bright);
    background: color-mix(in oklab, var(--brass) 8%, var(--card));
}
</style>
