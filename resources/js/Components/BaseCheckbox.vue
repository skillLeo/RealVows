<script setup>
import RvIcon from './RvIcon.vue';

/** 24px mark on a 44px tappable row. 8px radius, brass border when checked. */
defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <label class="rv-choice" :class="{ 'rv-choice--disabled': disabled }">
        <input
            type="checkbox"
            :checked="modelValue"
            :disabled="disabled"
            @change="$emit('update:modelValue', $event.target.checked)"
        />
        <span class="rv-choice__mark">
            <RvIcon v-if="modelValue" name="check" :size="14" :stroke-width="2" />
        </span>
        <span><slot /></span>
    </label>
</template>
