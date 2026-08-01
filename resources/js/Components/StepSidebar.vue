<script setup>
import RvIcon from './RvIcon.vue';

/**
 * The seven profile steps, in the agreed order and with the agreed names.
 * Current step sits in a filled brass pill, completed steps carry a small
 * brass check, upcoming steps stay in ink-soft.
 *
 * Hidden below 768px: on mobile the builder is a full-screen one-step-at-a-time
 * flow and position is carried by the header's "Step n of 7" plus the progress
 * bar. Rendering seven stacked rows above the form there would eat an entire
 * viewport — that was a real layout fault in an earlier pass.
 */
defineProps({
    steps: { type: Array, required: true },
    current: { type: Number, required: true },
});

defineEmits(['select']);
</script>

<template>
    <nav class="rv-steps" aria-label="Profile steps">
        <span class="rv-label">Your profile</span>

        <button
            v-for="(step, index) in steps"
            :key="step"
            type="button"
            class="rv-steps__row"
            :class="{
                'is-current': index === current,
                'is-done': index < current,
            }"
            :aria-current="index === current ? 'step' : undefined"
            @click="$emit('select', index)"
        >
            <span class="rv-steps__mark">
                <RvIcon v-if="index < current" name="check" :size="13" :stroke-width="2" />
                <template v-else>{{ index + 1 }}</template>
            </span>
            <span class="rv-steps__name">{{ step }}</span>
        </button>
    </nav>
</template>

<style scoped>
.rv-steps {
    display: none;
    flex-direction: column;
    gap: 4px;
    position: sticky;
    top: 104px;
}

@media (min-width: 768px) {
    .rv-steps {
        display: flex;
        flex: 0 0 260px;
        width: 260px;
    }
}

.rv-steps .rv-label {
    padding-bottom: 12px;
}

.rv-steps__row {
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 0 16px;
    border-radius: var(--r);
    border: 1px solid transparent;
    background: transparent;
    color: var(--ink-soft);
    font-size: var(--fs-body-2);
    text-align: left;
    cursor: pointer;
    transition:
        background var(--dur-tap) var(--ease),
        color var(--dur-tap) var(--ease),
        border-color var(--dur-tap) var(--ease);
}

.rv-steps__row.is-done {
    color: var(--ink);
}

.rv-steps__row.is-current {
    background: var(--brass);
    border-color: var(--brass);
    color: var(--oxblood-deep);
    font-weight: 500;
}

.rv-steps__name {
    flex: 1 1 auto;
}

.rv-steps__mark {
    flex: 0 0 auto;
    width: 24px;
    height: 24px;
    border-radius: var(--r);
    border: 1px solid var(--hairline);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--fs-body-3);
    line-height: 1;
    color: var(--ink-soft);
}

.rv-steps__row.is-done .rv-steps__mark {
    border-color: var(--brass);
    background: color-mix(in oklab, var(--brass) 12%, var(--card));
    color: var(--brass-ink);
}

.rv-steps__row.is-current .rv-steps__mark {
    border-color: var(--oxblood-deep);
    color: var(--oxblood-deep);
}
</style>
