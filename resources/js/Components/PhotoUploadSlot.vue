<script setup>
import { ref } from 'vue';
import RvIcon from './RvIcon.vue';

/**
 * One of exactly four photo slots. Never five, never six, never "up to".
 * The slot names are fixed by the specification and passed in.
 *
 * The image cross-fades in when an upload lands — a hard swap reads as a
 * glitch, a fade reads as the photo arriving.
 */
defineProps({
    index: { type: Number, required: true },
    title: { type: String, required: true },
    help: { type: String, required: true },
    preview: { type: String, default: null },
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['upload', 'remove']);

const input = ref(null);

function choose(event) {
    const file = event.target.files?.[0];
    if (file) emit('upload', file);
    // Reset so choosing the same file twice in a row still fires a change.
    event.target.value = '';
}
</script>

<template>
    <div class="rv-card rv-stack" style="gap: 20px">
        <div class="rv-slot__head">
            <span class="rv-slot__num">{{ String(index).padStart(2, '0') }}</span>
            <h3 class="rv-d3">{{ title }}</h3>
        </div>

        <div class="rv-slot__frame">
            <Transition name="rv-fade">
                <img
                    v-if="preview"
                    :key="preview"
                    :src="preview"
                    :alt="`Your ${title.toLowerCase()} photo`"
                    width="480"
                    height="600"
                    loading="lazy"
                    class="rv-slot__img"
                />
            </Transition>

            <span v-if="!preview" class="rv-slot__empty" aria-hidden="true">
                <RvIcon name="image" :size="28" />
            </span>

            <span v-if="preview" class="rv-pill rv-pill--on-dark rv-slot__flag">Uploaded</span>

            <input
                ref="input"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="rv-visually-hidden"
                :aria-label="`Upload your ${title.toLowerCase()} photo`"
                @change="choose"
            />

            <div class="rv-slot__actions">
                <button
                    type="button"
                    class="rv-btn rv-btn--on-dark rv-slot__action"
                    :disabled="busy"
                    @click="input.click()"
                >
                    {{ busy ? 'Uploading' : preview ? 'Replace' : 'Upload photo' }}
                </button>

                <button
                    v-if="preview && !busy"
                    type="button"
                    class="rv-slot__remove"
                    :aria-label="`Remove your ${title.toLowerCase()} photo`"
                    @click="emit('remove')"
                >
                    Remove
                </button>
            </div>
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
    gap: 12px;
    padding: 16px;
}

.rv-slot__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* 300ms cross-fade as the photo lands */
.rv-fade-enter-active,
.rv-fade-leave-active {
    transition: opacity 300ms var(--ease);
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

.rv-slot__actions {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.rv-slot__action {
    min-height: var(--tap-min);
    padding: 0 16px;
}

.rv-slot__remove {
    min-height: 36px;
    padding: 0 12px;
    border: 0;
    border-radius: var(--r);
    background: transparent;
    color: var(--brass-bright);
    font-size: var(--fs-body-3);
    cursor: pointer;
    transition: color var(--dur-tap) var(--ease);
}

.rv-slot__remove:hover {
    color: var(--cream);
}
</style>
