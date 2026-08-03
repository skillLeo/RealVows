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
    /**
     * ⚠️ Comes from settings, never from a constant. The company that
     * receives the money is not the name on the member's statement, and this
     * page is where somebody comes to check an unfamiliar charge.
     */
    billing: { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout title="Purchases">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Your account</span>
                <h1 class="rv-d2">{{ $t('purchases.heading') }}</h1>
                <span class="rv-underline" aria-hidden="true" />
                <p class="rv-lead" style="margin: 0">
                    You have {{ balance }} introduction<template v-if="balance !== 1">s</template> to open.
                </p>
            </header>

            <BaseCard v-if="!purchases.data.length">
                <div class="rv-stack" style="gap: 12px">
                    <h2 class="rv-d3">{{ $t('purchases.empty_heading') }}</h2>
                    <p class="rv-body">{{ $t('purchases.empty_body') }}</p>
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
                        <a
                            v-if="purchase.receiptUrl"
                            :href="purchase.receiptUrl"
                            class="rv-receipt__link"
                            target="_blank"
                            rel="noopener"
                        >{{ $t('purchases.receipt') }}</a>
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

            <!--
                ⚠️ THE SAME SENTENCE THE RECEIPT AND THE EMAIL CARRY.

                This is the page somebody opens when they see a name they do
                not recognise on a statement. It is the last place the answer
                can arrive before they phone their bank instead.
            -->
            <p v-if="billing.statementLine" class="rv-caption rv-purchases__billing">
                {{ billing.statementLine }}
                <template v-if="billing.tradingAsLine">
                    Payments are processed by {{ billing.tradingAsLine }}.
                </template>
                <template v-if="billing.supportEmail">
                    Questions about a charge: {{ billing.supportEmail }}
                </template>
            </p>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-purchases__billing {
    max-width: 60ch;
    color: var(--ink-soft);
}

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

.rv-receipt__link {
    color: var(--brass-ink);
    font-size: var(--fs-body-3);
    text-underline-offset: 3px;
}

.rv-lozenge--quiet {
    background: transparent;
    color: var(--ink-soft);
}
</style>
