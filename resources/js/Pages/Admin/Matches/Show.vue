<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import CompatibilityRing from '@/Components/CompatibilityRing.vue';

/**
 * One introduction, in full, for staff.
 *
 * ⚠️ The component names shown here are the INTERNAL ones. This page is
 * behind matches.view and is the only surface in the product where they
 * appear. Nothing on this screen may be copied into a member-facing view.
 */
defineProps({
    match: { type: Object, required: true },
    seeker: { type: Object, default: null },
    matched: { type: Object, default: null },
    components: { type: Object, required: true },
    redistributed: { type: Object, default: () => ({}) },
    missing: { type: Array, default: () => [] },
});
</script>

<template>
    <AppLayout title="Match detail">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header style="display: flex; align-items: center; gap: 32px; flex-wrap: wrap">
                <CompatibilityRing :score="Math.round(match.score)" />
                <div class="rv-stack" style="gap: 8px">
                    <span class="rv-label">Match #{{ match.id }}</span>
                    <h1 class="rv-d2">{{ seeker?.name }} → {{ matched?.name }}</h1>
                    <span class="rv-match__lozenge">{{ match.status }}</span>
                </div>
            </header>

            <BaseCard>
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-label">Score breakdown</span>

                    <table class="rv-table">
                        <thead>
                            <tr><th>Component</th><th>Raw</th><th>Weight</th><th>Weighted</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="(part, key) in components" :key="key">
                                <td>{{ key }}</td>
                                <td>{{ part.raw }}</td>
                                <td>
                                    {{ part.weight }}
                                    <span v-if="redistributed[key]" class="rv-small">
                                        ({{ redistributed[key] > 0 ? '+' : '' }}{{ redistributed[key] }})
                                    </span>
                                </td>
                                <td>{{ part.weighted }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="missing.length" class="rv-small" style="margin: 0">
                        No data for: {{ missing.join(', ') }}. Their weight was spread across the
                        components above in proportion, so the total still means out of 100.
                    </p>
                </div>
            </BaseCard>

            <BaseCard accent>
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label rv-label--brass">What the member reads</span>
                    <p class="rv-body" style="margin: 0">{{ match.rationale }}</p>
                    <ul style="margin: 0; padding-left: 18px">
                        <li v-for="line in match.commonalities" :key="line" class="rv-small">{{ line }}</li>
                    </ul>
                </div>
            </BaseCard>

            <BaseCard>
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">Timeline</span>
                    <div v-for="step in match.timeline" :key="step.label" class="rv-line">
                        <span class="rv-strong">{{ step.label }}</span>
                        <span class="rv-small">{{ step.at }}</span>
                    </div>
                    <p v-if="match.paidWithLedgerId" class="rv-small" style="margin: 0">
                        Paid for by ledger row #{{ match.paidWithLedgerId }}.
                    </p>
                </div>
            </BaseCard>

            <div class="rv-pair">
                <BaseCard v-for="side in [seeker, matched]" :key="side?.id">
                    <div class="rv-stack" style="gap: 8px">
                        <span class="rv-label">{{ side?.name }}</span>
                        <span class="rv-small">{{ side?.age }} · {{ side?.city }}, {{ side?.country }}</span>
                        <span class="rv-small">{{ side?.occupation }}</span>
                        <span class="rv-small">Faith: {{ side?.religion }}</span>
                        <span class="rv-small">Smoking: {{ side?.smoking }} · Drinking: {{ side?.drinking }}</span>
                        <span class="rv-small">Children: {{ side?.wantsChildren }} · Timeline: {{ side?.timeline }}</span>
                        <span class="rv-small">Values: {{ side?.coreValues?.join(', ') }}</span>
                    </div>
                </BaseCard>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-table { width: 100%; border-collapse: collapse; font-size: var(--fs-body-3); }
.rv-table th, .rv-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--hairline-soft); }
.rv-table th { text-transform: uppercase; letter-spacing: .14em; color: var(--ink-soft); font-weight: 500; }
.rv-table td { font-variant-numeric: tabular-nums; }
.rv-line { display: flex; justify-content: space-between; gap: 16px; }
</style>
