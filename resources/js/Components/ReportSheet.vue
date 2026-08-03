<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import BaseButton from './BaseButton.vue';

/**
 * Reporting a concern.
 *
 * Quiet on purpose — a bottom sheet reached from a ghost action, never a loud
 * red button. This is a marriage platform, not a moderation product, and
 * making the report path prominent would suggest members should expect
 * trouble.
 *
 * Native <dialog>, so focus trapping, inertness and the escape key are the
 * browser's job rather than ours.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    matchId: { type: [Number, String], required: true },
    name: { type: String, default: 'them' },
});

const emit = defineEmits(['update:open']);

const sheet = ref(null);
const sent = ref(false);

const REASONS = [
    { value: 'misrepresented_identity', label: 'They are not who they say they are' },
    { value: 'inappropriate_content', label: 'Something in their profile is inappropriate' },
    { value: 'appears_to_be_scam', label: 'This looks like a scam' },
    { value: 'other', label: 'Something else' },
];

const form = useForm({ reason: '', note: '' });

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            sent.value = false;
            form.reset();
            form.clearErrors();
            sheet.value?.showModal();

            // Back closes the sheet rather than navigating away.
            history.pushState({ rvSheet: true }, '');
            window.addEventListener('popstate', closeFromHistory);
        } else {
            sheet.value?.close();
        }
    }
);

function closeFromHistory() {
    emit('update:open', false);
    window.removeEventListener('popstate', closeFromHistory);
}

function close() {
    emit('update:open', false);
    if (history.state?.rvSheet) history.back();
    window.removeEventListener('popstate', closeFromHistory);
}

function submit() {
    form.post(`/matches/${props.matchId}/report`, {
        preserveScroll: true,
        onSuccess: () => {
            sent.value = true;
        },
    });
}
</script>

<template>
    <dialog ref="sheet" class="rv-sheet" @close="emit('update:open', false)" @click.self="close">
        <span class="rv-sheet__grip" aria-hidden="true" />

        <div class="rv-sheet__body">
            <!-- Confirmed with certainty and no drama. -->
            <div v-if="sent" class="rv-stack" style="gap: 16px">
                <h2 class="rv-d3">{{ $t('checkout.thanks') }}</h2>
                <p class="rv-body" style="margin: 0">
                    Someone will look at this. We will not tell {{ name }} that you got in touch.
                </p>
                <BaseButton variant="primary" @click="close">Close</BaseButton>
            </div>

            <form v-else class="rv-stack" style="gap: 20px" @submit.prevent="submit">
                <h2 class="rv-d3">{{ $t('report.heading') }}</h2>
                <p class="rv-small" style="margin: 0">
                    This goes to a person on our team. {{ name }} is never told.
                </p>

                <div class="rv-stack" style="gap: 8px" role="radiogroup" aria-label="What is the concern">
                    <label v-for="option in REASONS" :key="option.value" class="rv-choice">
                        <input v-model="form.reason" type="radio" :value="option.value" name="reason" />
                        <span class="rv-choice__mark" aria-hidden="true" />
                        <span>{{ option.label }}</span>
                    </label>
                </div>

                <p v-if="form.errors.reason" class="rv-error" role="alert">{{ form.errors.reason }}</p>

                <label class="rv-field">
                    <span class="rv-label">{{ $t('report.anything_you_want_to') }}</span>
                    <textarea
                        v-model="form.note"
                        class="rv-input rv-textarea"
                        rows="4"
                        placeholder="Optional"
                    />
                </label>

                <div class="rv-actions">
                    <BaseButton type="submit" variant="primary" :disabled="form.processing">
                        {{ form.processing ? 'Sending' : 'Send' }}
                    </BaseButton>
                    <BaseButton type="button" variant="ghost" @click="close">Cancel</BaseButton>
                </div>
            </form>
        </div>
    </dialog>
</template>
