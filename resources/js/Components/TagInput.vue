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

    /*
     * ⚠️ 44px BECAUSE THE BUTTON INSIDE IT REMOVES SOMETHING.
     *
     * This was 36px, which capped the remove button at 24px — measured at 375,
     * 834 and 1440 and undersized at all three. A 24px target on a phone is a
     * coin toss, and the cost of losing it is not a missed tap: it is an
     * interest silently deleted, in a form somebody is halfway through.
     */
    height: 44px;
    padding: 0 8px 0 14px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
    font-size: var(--fs-body-3);
    color: var(--ink);
}

.rv-tags__item button {
    /*
     * ⚠️ THE HIT AREA IS THE BUTTON, NOT A PSEUDO-ELEMENT.
     *
     * A ::after overlay would give a person the same 44px to tap and would
     * leave the element itself measuring 24px, so every automated check —
     * including scripts/measure-viewports.mjs — would keep reporting a failure
     * that had been fixed. The background is transparent, so this looks exactly
     * as it did: only the icon is visible.
     *
     * ⚠️ TALLER, NOT WIDER. Chips sit 8px apart horizontally, so a 44px-wide
     * target would reach across into the next chip's label and a tap meant for
     * one would delete another.
     */
    width: 32px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: var(--r);
    background: transparent;
    color: var(--brass-ink);
    cursor: pointer;
    transition: color var(--dur-tap) var(--ease);
}

.rv-tags__item button:hover {
    color: var(--rose);
}
</style>
