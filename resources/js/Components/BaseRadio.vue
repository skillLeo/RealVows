<script setup>
/**
 * Radios keep the 8px radius — they are rounded squares carrying an inset
 * brass square, never circles. This is a locked rule of the design system.
 */
defineProps({
    modelValue: { type: [String, Number, Boolean, null], default: null },
    value: { type: [String, Number, Boolean], required: true },
    name: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="rv-choice rv-choice--radio" :class="{ 'rv-choice--disabled': disabled }">
        <input
            type="radio"
            :name="name"
            :value="value"
            :checked="modelValue === value"
            :disabled="disabled"
            @change="$emit('update:modelValue', value)"
        />
        <span class="rv-choice__mark">
            <span v-if="modelValue === value" class="rv-choice__dot" />
        </span>
        <span><slot /></span>
    </label>
</template>
