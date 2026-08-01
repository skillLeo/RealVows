<script setup>
import RvIcon from './RvIcon.vue';

/** Brass only. Never green, never a seal. */
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
