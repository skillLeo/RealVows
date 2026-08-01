<script setup>
import RvIcon from './RvIcon.vue';

/**
 * One of exactly four photo slots. Never five, never six, never "up to".
 * The slot names are fixed by the specification and are passed in.
 */
defineProps({
    index: { type: Number, required: true },
    title: { type: String, required: true },
    help: { type: String, required: true },
    preview: { type: String, default: null },
});

defineEmits(['upload']);
</script>

<template>
    <div class="rv-card rv-stack" style="gap: 20px">
        <div class="rv-slot__head">
            <span class="rv-slot__num">{{ String(index).padStart(2, '0') }}</span>
            <h3 class="rv-d3">{{ title }}</h3>
        </div>

        <div class="rv-slot__frame">
            <img v-if="preview" :src="preview" alt="" width="480" height="600" loading="lazy" class="rv-slot__img" />

            <template v-else>
                <span class="rv-slot__empty">
                    <RvIcon name="image" :size="28" />
                </span>
            </template>

            <span v-if="preview" class="rv-pill rv-pill--on-dark rv-slot__flag">Uploaded</span>

            <button type="button" class="rv-btn rv-btn--on-dark rv-slot__action" @click="$emit('upload', index)">
                {{ preview ? 'Replace photo' : 'Upload photo' }}
            </button>
        </div>

        <p class="rv-small">{{ help }}</p>
    </div>
</template>

<style scoped>
.rv-slot__head {
    display: flex;
    align-items: baseline;
    gap: 12px;
}

.rv-slot__num {
    font-family: var(--display);
    font-size: var(--fs-body-1);
    line-height: 1;
    color: var(--brass);
}

.rv-slot__frame {
    position: relative;
    overflow: hidden;
    aspect-ratio: 4 / 5;
    border-radius: var(--r);
    border: 1px solid var(--hairline);
    background: linear-gradient(160deg, var(--oxblood) 0%, var(--oxblood-deep) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    padding: 16px;
}

.rv-slot__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: opacity var(--dur-enter) var(--ease);
}

.rv-slot__empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(185, 145, 84, 0.42);
}

.rv-slot__flag {
    position: absolute;
    left: 12px;
    top: 12px;
}

.rv-slot__action {
    position: relative;
    min-height: var(--tap-min);
    padding: 0 16px;
}
</style>
