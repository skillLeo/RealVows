<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

/**
 * A holding page, not the packages screen.
 *
 * Packages belong to Phase 2, when there is a working purchase path behind
 * them. But the tab bar has four tabs and a member will tap this one, so it
 * says something true and reassuring rather than returning a 404 — the
 * prices are real and already agreed, and the free-signup promise is
 * restated here because it is the thing the client most wants understood.
 */
defineProps({
    completeness: { type: Number, default: 0 },
});

const PACKAGES = [
    { matches: '1 match', price: 'US$20', label: 'Most flexible' },
    { matches: '3 matches', price: 'US$50', label: 'Best value' },
    { matches: '8 matches', price: 'US$100', label: 'For serious seekers' },
];
</script>

<template>
    <AppLayout title="Match packages">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Match packages</span>
                <h1 class="rv-d2">Your profile is free, and it stays ready.</h1>
                <p class="rv-lead" style="max-width: 56ch">
                    Building your whole profile costs nothing. You only choose a package when you
                    would like us to introduce you to someone, and every package is a one-time fee —
                    there are no subscriptions anywhere in RealVows.
                </p>
            </header>

            <div class="rv-grid rv-grid--3">
                <BaseCard v-for="pack in PACKAGES" :key="pack.matches" :accent="pack.label === 'Best value'">
                    <div class="rv-stack" style="gap: 20px">
                        <span class="rv-pill" :class="{ 'rv-pill--solid': pack.label === 'Best value' }">
                            {{ pack.label }}
                        </span>
                        <h2 class="rv-d3">{{ pack.matches }}</h2>
                        <div class="rv-pack__price">
                            <span class="rv-numeral rv-pack__amount">{{ pack.price }}</span>
                            <span class="rv-small">one-time</span>
                        </div>
                    </div>
                </BaseCard>
            </div>

            <BaseCard accent>
                <div class="rv-stack" style="gap: 16px">
                    <h2 class="rv-d3">Checkout opens with your first matches.</h2>
                    <p class="rv-body" style="max-width: 60ch">
                        Finish your profile and get verified first. Once a real person has approved
                        you, RealVows finds the people you are most compatible with — and that is
                        when choosing a package starts to mean something.
                    </p>
                    <div>
                        <BaseButton href="/profile" variant="primary">
                            {{ completeness === 100 ? 'Review your profile' : 'Continue your profile' }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-pack__price {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.rv-pack__amount {
    font-size: var(--fs-display-2);
    letter-spacing: -0.01em;
}
</style>
