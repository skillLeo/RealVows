<script setup>
import BaseCard from '@/Components/BaseCard.vue';
import VerifiedBadge from '@/Components/VerifiedBadge.vue';
import StepShell from './StepShell.vue';

/**
 * Read-only. Email and mobile were captured at signup; changing either has
 * consequences (a new confirmation, a different WhatsApp destination) that
 * belong on the Account screen, not inside a form the member is filling in.
 */
defineProps({
    account: { type: Object, required: true },
});
</script>

<template>
    <StepShell
        heading="Your account"
        lead="The details you signed up with. Everything else is on the steps that follow."
    >
        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <div class="rv-account__row">
                    <div class="rv-stack" style="gap: 6px">
                        <span class="rv-label">Email address</span>
                        <span class="rv-account__value">{{ account.email }}</span>
                    </div>
                    <VerifiedBadge v-if="account.email_verified" label="Confirmed" />
                    <span v-else class="rv-pill">Not confirmed yet</span>
                </div>

                <span class="rv-rule rv-rule--soft" />

                <div class="rv-account__row">
                    <div class="rv-stack" style="gap: 6px">
                        <span class="rv-label">Mobile number</span>
                        <span class="rv-account__value">
                            {{ account.phone_country_code }} {{ account.phone_number }}
                        </span>
                        <span class="rv-help">We send your matches here by WhatsApp.</span>
                    </div>
                    <VerifiedBadge v-if="account.phone_verified" label="Verified" />
                    <span v-else class="rv-pill">Not verified yet</span>
                </div>
            </div>
        </BaseCard>

        <p class="rv-small">
            Need to change either of these? You can do it from your account settings.
        </p>
    </StepShell>
</template>

<style scoped>
.rv-account__row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px 20px;
}

.rv-account__value {
    font-size: var(--fs-body-2);
    font-weight: 500;
}
</style>
