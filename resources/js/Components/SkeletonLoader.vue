<script setup>
/**
 * Skeleton loaders, never spinners, for anything over 200ms.
 * A spinner says "wait"; a skeleton says "here is the shape of what's coming".
 */
defineProps({
    lines: { type: Number, default: 3 },
    height: { type: String, default: '16px' },
    block: { type: Boolean, default: false },
});
</script>

<template>
    <div class="rv-skeleton-set" role="status" aria-label="Loading">
        <template v-if="block">
            <span class="rv-skeleton rv-skeleton-set__block" />
        </template>
        <template v-else>
            <span
                v-for="line in lines"
                :key="line"
                class="rv-skeleton"
                :style="{ height, width: line === lines ? '62%' : '100%' }"
            />
        </template>
        <span class="rv-visually-hidden">Loading</span>
    </div>
</template>

<style scoped>
.rv-skeleton-set {
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 100%;
}

.rv-skeleton-set__block {
    width: 100%;
    aspect-ratio: 4 / 5;
}
</style>
