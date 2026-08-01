<script setup>
/**
 * The frame every step sits in: a display heading, one line of orientation,
 * then the fields. One idea per area of screen — the heading says what this
 * step is for and nothing competes with it.
 */
defineProps({
    heading: { type: String, required: true },
    lead: { type: String, default: '' },
});
</script>

<template>
    <section class="rv-stack" style="gap: 32px">
        <header class="rv-stack" style="gap: 12px">
            <h1 class="rv-d2">{{ heading }}</h1>
            <p v-if="lead" class="rv-lead" style="max-width: 52ch">{{ lead }}</p>
        </header>

        <slot />
    </section>
</template>
