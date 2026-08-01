<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

/**
 * Failed attempts are listed too. Hiding them makes a member who remembers a
 * decline think the record is wrong, which is the opposite of what a payment
 * history is for.
 */
defineProps({
    purchases: { type: Object, required: true },
    balance: { type: Number, default: 0 },
});
</script>

<template>
    <AppLayout title="Purchases">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Your account</span>
                <h1 class="rv-d2">Purchase history</h1>
                <span class="rv-underline" aria-hidden="true" />
                <p class="rv-lead" style="margin: 0">
                    You have {{ balance }} introduction<template v-if="balance !== 1">s</template> to open.
                </p>
            </header>

            <BaseCard v-if="!purchases.data.length">
                <div class="rv-stack" style="gap: 12px">
                    <h2 class="rv-d3">Nothing here yet.</h2>
                    <p class="rv-body">Your receipts will appear here once you open your first introduction.</p>
                    <BaseButton href="/packages" variant="secondary">See packages</BaseButton>
                </div>
            </BaseCard>

            <div v-else class="rv-stack" style="gap: 16px">
                <article
                    v-for="purchase in purchases.data"
                    :key="purchase.reference"
                    class="rv-receipt rv-arrive"
                >
                    <div class="rv-stack" style="gap: 4px">
                        <span class="rv-strong">{{ purchase.reference }}</span>
                        <span class="rv-small">{{ purchase.date }}</span>
                    </div>

                    <div class="rv-receipt__right">
                        <span class="rv-strong">{{ purchase.amount }}</span>
                        <span
                            class="rv-match__lozenge"
                            :class="{ 'rv-lozenge--quiet': !purchase.succeeded }"
                        >
                            {{ purchase.succeeded ? `${purchase.credits} opened` : 'Not completed' }}
                        </span>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-receipt {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 24px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
}

.rv-receipt__right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.rv-lozenge--quiet {
    background: transparent;
    color: var(--ink-soft);
}
</style>
