<script setup>
import { onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

/**
 * Success routes straight into the unsealing on the dashboard.
 *
 * Failure gets a calm, specific, non-blaming screen. Failed payments are
 * where members decide whether to trust a platform with money, so this
 * matters as much as the success — and the one thing it must say, in the
 * strongest terms available, is that nothing was charged.
 */
const props = defineProps({
    succeeded: { type: Boolean, required: true },
    reference: { type: String, required: true },
    credits: { type: Number, default: 0 },
    amount: { type: String, default: '' },
    balance: { type: Number, default: 0 },
    failureMessage: { type: String, default: null },
    packageKey: { type: String, default: 'triple' },
});

onMounted(() => {
    if (props.succeeded) {
        // Straight to the ceremony. The state is already committed; this is
        // only choosing where the member watches it happen.
        setTimeout(() => router.visit('/dashboard'), 1200);
    }
});
</script>

<template>
    <AppLayout :title="succeeded ? 'Thank you' : 'Payment not completed'">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px; max-width: 620px">
            <template v-if="succeeded">
                <header class="rv-stack" style="gap: 12px">
                    <span class="rv-label rv-label--brass">{{ reference }}</span>
                    <h1 class="rv-d1">Thank you.</h1>
                    <span class="rv-underline" aria-hidden="true" />
                </header>

                <BaseCard accent>
                    <div class="rv-stack" style="gap: 16px">
                        <p class="rv-body" style="margin: 0">
                            {{ amount }} paid. You have {{ balance }} introduction<template v-if="balance !== 1">s</template>
                            to open, and a receipt is on its way to your inbox.
                        </p>
                        <p class="rv-small" style="margin: 0">Taking you to them now.</p>
                    </div>
                </BaseCard>
            </template>

            <template v-else>
                <header class="rv-stack" style="gap: 12px">
                    <span class="rv-label">{{ reference }}</span>
                    <h1 class="rv-d2">That didn't go through.</h1>
                    <span class="rv-underline" aria-hidden="true" />
                </header>

                <BaseCard>
                    <div class="rv-stack" style="gap: 20px">
                        <p class="rv-body" style="margin: 0">{{ failureMessage }}</p>

                        <p class="rv-strong" style="margin: 0">
                            You have not been charged. There is nothing to cancel and nothing to claim
                            back.
                        </p>

                        <div class="rv-actions">
                            <BaseButton :href="`/checkout/${packageKey}`" variant="primary">
                                Try again
                            </BaseButton>
                            <BaseButton href="/packages" variant="secondary">
                                Choose a different package
                            </BaseButton>
                        </div>

                        <p class="rv-small" style="margin: 0">
                            Your profile is untouched and your introductions are still waiting.
                        </p>
                    </div>
                </BaseCard>
            </template>
        </div>
    </AppLayout>
</template>
