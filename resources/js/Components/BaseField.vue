<script setup>
import { computed, useId } from 'vue';

/**
 * Label above in tracked caps 14, 56px control, helper text below in 14 —
 * one line, never repeating the label. Error state takes rose and the
 * geometry never changes.
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    help: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    autocomplete: { type: String, default: null },
    inputmode: { type: String, default: null },
    required: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);

const id = useId();
const helpId = computed(() => `${id}-help`);
const message = computed(() => props.error || props.help);
</script>

<template>
    <div class="rv-field" :class="{ 'rv-field--error': !!error }">
        <div class="rv-field__head">
            <label :for="id" class="rv-label">{{ label }}</label>
            <slot name="aside" />
        </div>

        <input
            :id="id"
            class="rv-input"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :required="required"
            :aria-describedby="message ? helpId : undefined"
            :aria-invalid="error ? 'true' : undefined"
            @input="$emit('update:modelValue', $event.target.value)"
        />

        <span v-if="message" :id="helpId" class="rv-help">{{ message }}</span>
    </div>
</template>
