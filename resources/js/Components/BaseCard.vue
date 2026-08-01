<script setup>
/** Hairline brass border, 32px internal padding, never a drop shadow. */
defineProps({
    accent: { type: Boolean, default: false },
    lift: { type: Boolean, default: false },
    flush: { type: Boolean, default: false },
    quiet: { type: Boolean, default: false },
    as: { type: String, default: 'div' },
});
</script>

<template>
    <component
        :is="as"
        class="rv-card"
        :class="{
            'rv-card--accent': accent,
            'rv-card--lift': lift,
            'rv-card--flush': flush,
            'rv-card--quiet': quiet,
        }"
    >
        <slot />
    </component>
</template>
