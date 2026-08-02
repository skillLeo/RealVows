<script setup>
/**
 * The Seal.
 *
 * One motif, used everywhere: intact on a locked card, split during the
 * unsealing, quiet on a receipt, and the same disc geometry as the verified
 * badge. One idea repeated is what a design system is; five ideas scattered
 * is what generated work looks like.
 *
 * The pressed relief is two pseudo-elements — a lit upper arc and a shaded
 * lower one — because shadows are banned and light falling from above is how
 * a real pressed seal reads.
 */
defineProps({
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    // Renders the two halves so the unsealing can part them.
    breakable: { type: Boolean, default: false },
    onFlap: { type: Boolean, default: false },
});
</script>

<template>
    <span
        class="rv-seal"
        :class="[
            size === 'sm' && 'rv-seal--sm',
            size === 'lg' && 'rv-seal--lg',
            onFlap && 'rv-seal--flap',
        ]"
        aria-hidden="true"
    >
        <template v-if="breakable">
            <span class="rv-seal__half rv-seal__half--l" />
            <span class="rv-seal__half rv-seal__half--r" />
        </template>
        RV
    </span>
</template>
