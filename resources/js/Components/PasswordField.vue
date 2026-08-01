<script setup>
import { computed, ref, useId } from 'vue';
import RvIcon from './RvIcon.vue';

/**
 * Password field with the show/hide control sitting inside the field frame,
 * on the right. Keeps the 56px height and 8px radius in every state.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Password' },
    placeholder: { type: String, default: 'At least 8 characters' },
    help: { type: String, default: '' },
    error: { type: String, default: '' },
    autocomplete: { type: String, default: 'current-password' },
});

defineEmits(['update:modelValue']);

const id = useId();
const helpId = computed(() => `${id}-help`);
const revealed = ref(false);
const message = computed(() => props.error || props.help);
</script>

<template>
    <div class="rv-field" :class="{ 'rv-field--error': !!error }">
        <div class="rv-field__head">
            <label :for="id" class="rv-label">{{ label }}</label>
            <slot name="aside" />
        </div>

        <div class="rv-input-group">
            <input
                :id="id"
                :type="revealed ? 'text' : 'password'"
                :value="modelValue"
                :placeholder="placeholder"
                :autocomplete="autocomplete"
                :aria-describedby="message ? helpId : undefined"
                :aria-invalid="error ? 'true' : undefined"
                @input="$emit('update:modelValue', $event.target.value)"
            />
            <button
                type="button"
                class="rv-input-trailing"
                :aria-label="revealed ? 'Hide password' : 'Show password'"
                :aria-pressed="revealed"
                @click="revealed = !revealed"
            >
                <RvIcon :name="revealed ? 'eye-off' : 'eye'" :size="18" />
            </button>
        </div>

        <span v-if="message" :id="helpId" class="rv-help">{{ message }}</span>
    </div>
</template>
