<script setup>
import RvIcon from './RvIcon.vue';

/**
 * Brass only. Never green, never a seal.
 *
 * ⚠️ THE SEAL DOES NOT BELONG HERE, AND THIS HAS BEEN SETTLED ONCE ALREADY.
 *
 * Addendum C asked for the seal in five placements, this badge among them.
 * That instruction was withdrawn after review, and the reasoning is worth
 * keeping because the request is an easy one to make twice:
 *
 * The seal means SEALED AND UNOPENED. Verification means THIS PERSON IS REAL.
 * They are different ideas, and a single mark carrying both carries neither —
 * a member who has learnt that brass disc means "not yet opened" would read a
 * sealed badge on a verified profile as something withheld.
 *
 * Four placements, not five: the locked teaser card, the home page demo card,
 * the receipt, and the app icon.
 */
defineProps({
    label: { type: String, default: 'Verified' },
    size: { type: String, default: 'sm', validator: (v) => ['sm', 'lg'].includes(v) },
});
</script>

<template>
    <span class="rv-pill" :class="{ 'rv-pill--lg': size === 'lg' }">
        <RvIcon name="check" :size="size === 'lg' ? 16 : 14" :stroke-width="1.75" />
        <span>{{ label }}</span>
    </span>
</template>
