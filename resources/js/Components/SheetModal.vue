<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Sheets, not modals, on mobile: slides up from the bottom with a drag
 * handle. At 768px and above it becomes a centred dialog. Either way it
 * traps focus, closes on Escape, and locks background scroll.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
});

const emit = defineEmits(['close']);

const panel = ref(null);
let previouslyFocused = null;

function onKeydown(event) {
    if (!props.open) return;

    if (event.key === 'Escape') {
        emit('close');
        return;
    }

    if (event.key !== 'Tab') return;

    const focusable = panel.value?.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    );
    if (!focusable?.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.open,
    async (isOpen) => {
        document.body.style.overflow = isOpen ? 'hidden' : '';

        if (isOpen) {
            previouslyFocused = document.activeElement;
            await new Promise((resolve) => requestAnimationFrame(resolve));
            panel.value?.focus();
        } else {
            previouslyFocused?.focus?.();
        }
    }
);

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="rv-fade">
            <div v-if="open" class="rv-sheet__scrim" @click="$emit('close')" />
        </Transition>

        <Transition name="rv-sheet">
            <div
                v-if="open"
                ref="panel"
                class="rv-sheet"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                tabindex="-1"
            >
                <span class="rv-sheet__handle" aria-hidden="true" />
                <h2 class="rv-d3 rv-sheet__title">{{ title }}</h2>
                <div class="rv-sheet__body">
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.rv-sheet__scrim {
    position: fixed;
    inset: 0;
    z-index: 60;
    background: color-mix(in oklab, var(--oxblood-deep) 62%, transparent);
}

.rv-sheet {
    position: fixed;
    z-index: 61;
    left: 0;
    right: 0;
    bottom: 0;
    max-height: 88vh;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
    display: flex;
    flex-direction: column;
    gap: 24px;
    background: var(--card);
    border-top: 1px solid var(--brass);
    border-radius: var(--r) var(--r) 0 0;
    padding: 16px var(--pad-card) calc(var(--pad-card) + env(safe-area-inset-bottom, 0px));
    outline: none;
}

.rv-sheet__handle {
    align-self: center;
    width: 44px;
    height: 4px;
    border-radius: 2px;
    background: var(--hairline);
}

.rv-sheet__title {
    margin: 0;
}

@media (min-width: 768px) {
    .rv-sheet {
        left: 50%;
        top: 50%;
        right: auto;
        bottom: auto;
        width: min(560px, calc(100vw - 48px));
        transform: translate(-50%, -50%);
        border: 1px solid var(--brass);
        border-radius: var(--r);
        padding: var(--pad-card);
    }

    .rv-sheet__handle {
        display: none;
    }

    .rv-sheet-enter-from,
    .rv-sheet-leave-to {
        transform: translate(-50%, -46%);
        opacity: 0;
    }
}
</style>
