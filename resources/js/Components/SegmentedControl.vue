<script setup>
import { useId } from 'vue';

/**
 * Four or fewer options is always a segmented control, never a dropdown.
 * The active segment fills oxblood with a brass-bright hairline.
 *
 * Options may be plain strings or { value, label } pairs.
 */
const props = defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: null },
    options: { type: Array, required: true },
    label: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

const id = useId();

const valueOf = (option) => (typeof option === 'object' && option !== null ? option.value : option);
const labelOf = (option) => (typeof option === 'object' && option !== null ? option.label : option);
</script>

<template>
    <div class="rv-stack" style="gap: 12px">
        <span v-if="label" :id="id" class="rv-label">{{ label }}</span>
        <div class="rv-seg" role="group" :aria-labelledby="label ? id : undefined">
            <button
                v-for="option in options"
                :key="valueOf(option)"
                type="button"
                class="rv-seg__option"
                :aria-pressed="modelValue === valueOf(option)"
                @click="$emit('update:modelValue', valueOf(option))"
            >
                {{ labelOf(option) }}
            </button>
        </div>
    </div>
</template>
