<script setup>
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

/**
 * Order summary. The package, the price, what they get, one action.
 *
 * Deliberately nothing else on this screen — no upsell, no cross-sell, no
 * "people also bought". Somebody about to spend money on finding a husband
 * should not be handled like a shopping cart.
 */
const props = defineProps({
    package: { type: Object, required: true },
    credits: { type: Number, default: 0 },
});

const form = useForm({});

function pay() {
    form.post(`/checkout/${props.package.key}`);
}
</script>

<template>
    <AppLayout title="Checkout">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px; max-width: 620px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Checkout</span>
                <h1 class="rv-d2">{{ package.label }}</h1>
                <span class="rv-underline" aria-hidden="true" />
            </header>

            <BaseCard>
                <div class="rv-stack" style="gap: 20px">
                    <div class="rv-line">
                        <span>{{ package.credits }} introduction<template v-if="package.credits !== 1">s</template></span>
                        <span class="rv-package__price">{{ package.price }}</span>
                    </div>

                    <p class="rv-small" style="margin: 0">
                        {{ $t('packages.one_payment') }}
                    </p>

                    <BaseButton variant="primary" block :disabled="form.processing" @click="pay">
                        {{ form.processing ? 'Taking you to your bank' : `Pay ${package.price}` }}
                    </BaseButton>

                    <p class="rv-small" style="margin: 0; text-align: center">
                        {{ $t('packages.card_safety') }}
                    </p>
                </div>
            </BaseCard>

            <p class="rv-small">
                You currently have {{ credits }} introduction<template v-if="credits !== 1">s</template> to open.
            </p>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-line {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    font-size: var(--fs-body-1);
}
</style>
