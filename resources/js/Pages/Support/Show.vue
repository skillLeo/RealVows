<script setup>
import { onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    types: { type: Array, required: true },
    suggestUnfair: { type: Boolean, default: false },
    open: { type: Array, default: () => [] },
});

const page = usePage();
const form = useForm({ type: props.types[0]?.value ?? 'other', body: '' });

/**
 * ⚠️ PRE-SELECTED WHEN THE PATTERN IS THERE (B9).
 *
 * A member being declined repeatedly with no reason cannot see the decisions at
 * all, so she has no way of knowing this route exists. Waiting for her to think
 * of it is waiting for somebody to complain about a process nobody showed her.
 */
onMounted(() => {
    if (props.suggestUnfair) form.type = 'guardian_unfair';
});
</script>

<template>
    <AppLayout title="Support">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <h1 class="rv-d2">{{ $t('support.heading') }}</h1>
                <p class="rv-lead" style="max-width: 54ch">{{ $t('support.lead') }}</p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <BaseCard v-if="suggestUnfair" accent>
                <p class="rv-body">{{ $t('support.unfair_prompt') }}</p>
            </BaseCard>

            <BaseCard>
                <form class="rv-stack" style="gap: 24px" @submit.prevent="form.post('/support', { preserveScroll: true, onSuccess: () => form.reset('body') })">
                    <div class="rv-field">
                        <label class="rv-label" for="rv-support-type">
                            {{ $t('support.what_about') }}
                        </label>
                        <select id="rv-support-type" v-model="form.type" class="rv-select">
                            <option v-for="type in types" :key="type.value" :value="type.value">
                                {{ type.label }}
                            </option>
                        </select>
                    </div>

                    <div class="rv-field">
                        <label class="rv-label" for="rv-support-body">
                            {{ $t('support.tell_us') }}
                        </label>
                        <textarea
                            id="rv-support-body"
                            v-model="form.body"
                            class="rv-input rv-textarea"
                            rows="6"
                        />
                        <span v-if="form.errors.body" class="rv-help" style="color: var(--rose)">
                            {{ form.errors.body }}
                        </span>
                    </div>

                    <div class="rv-actions">
                        <BaseButton type="submit" variant="primary" :disabled="form.processing">
                            {{ form.processing ? $t('support.sending') : $t('support.send') }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseCard v-if="open.length">
                <div class="rv-stack" style="gap: 12px">
                    <h2 class="rv-d3">{{ $t('support.open_heading') }}</h2>
                    <p v-for="item in open" :key="item.id" class="rv-help">
                        {{ item.label }} · {{ item.at }}
                    </p>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>
