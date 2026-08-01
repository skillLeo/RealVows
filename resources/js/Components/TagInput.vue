<script setup>
import { ref, useId } from 'vue';
import RvIcon from './RvIcon.vue';

/**
 * Free-text tags — languages, interests, qualities someone is looking for.
 * Enter or comma commits; backspace on an empty field removes the last one.
 */
const model = defineModel({ type: Array, default: () => [] });

const props = defineProps({
    label: { type: String, required: true },
    placeholder: { type: String, default: '' },
    help: { type: String, default: '' },
    max: { type: Number, default: 12 },
});

const id = useId();
const draft = ref('');

function commit() {
    const value = draft.value.trim().replace(/,+$/, '');

    if (!value) return;
    if (model.value.length >= props.max) return;
    if (model.value.some((tag) => tag.toLowerCase() === value.toLowerCase())) {
        draft.value = '';
        return;
    }

    model.value = [...model.value, value];
    draft.value = '';
}

function remove(index) {
    model.value = model.value.filter((_, i) => i !== index);
}

function onKeydown(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        commit();
        return;
    }

    if (event.key === 'Backspace' && draft.value === '' && model.value.length) {
        model.value = model.value.slice(0, -1);
    }
}
</script>

<template>
    <div class="rv-field">
        <div class="rv-field__head">
            <label :for="id" class="rv-label">{{ label }}</label>
            <span class="rv-help">{{ model.length }} of {{ max }}</span>
        </div>

        <ul v-if="model.length" class="rv-tags">
            <li v-for="(tag, index) in model" :key="tag" class="rv-tags__item">
                <span>{{ tag }}</span>
                <button type="button" :aria-label="`Remove ${tag}`" @click="remove(index)">
                    <RvIcon name="eye-off" :size="12" :stroke-width="1.75" />
                </button>
            </li>
        </ul>

        <input
            :id="id"
            v-model="draft"
            class="rv-input"
            type="text"
            :placeholder="model.length >= max ? 'That is the maximum' : placeholder"
            :disabled="model.length >= max"
            @keydown="onKeydown"
            @blur="commit"
        />

        <span v-if="help" class="rv-help">{{ help }}</span>
    </div>
</template>

<style scoped>
.rv-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.rv-tags__item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 36px;
    padding: 0 8px 0 14px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
    font-size: var(--fs-body-3);
    color: var(--ink);
}

.rv-tags__item button {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: var(--r);
    background: transparent;
    color: var(--brass);
    cursor: pointer;
    transition: color var(--dur-tap) var(--ease);
}

.rv-tags__item button:hover {
    color: var(--rose);
}
</style>
