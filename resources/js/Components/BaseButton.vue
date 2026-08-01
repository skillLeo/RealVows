<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * 56px tall, 8px radius, hairline border, scales to 0.98 on press.
 * Renders as an Inertia <Link> when given `href`, otherwise a <button>.
 */
const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) =>
            ['primary', 'secondary', 'ghost', 'outline-brass', 'on-dark', 'ghost-on-dark'].includes(v),
    },
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    block: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const classes = computed(() => [
    'rv-btn',
    `rv-btn--${props.variant}`,
    { 'rv-btn--block': props.block },
]);
</script>

<template>
    <Link v-if="href && !disabled" :href="href" :class="classes">
        <slot />
    </Link>
    <span v-else-if="href" :class="classes" aria-disabled="true">
        <slot />
    </span>
    <button v-else :type="type" :class="classes" :disabled="disabled">
        <slot />
    </button>
</template>
