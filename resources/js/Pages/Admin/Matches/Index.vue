<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import RvIcon from '@/Components/RvIcon.vue';

const props = defineProps({
    matches: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const tabs = [
    { key: '', label: 'All', count: 'all' },
    { key: 'teaser', label: 'Locked', count: 'teaser' },
    { key: 'unlocked', label: 'Opened', count: 'unlocked' },
    { key: 'expired', label: 'Expired', count: 'expired' },
];

function sortBy(sort) {
    router.get('/admin/matches', { ...props.filters, sort }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Match log">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Match log</h1>
                <p class="rv-lead" style="max-width: 56ch">
                    Every introduction the engine has generated, with its full working. This is the
                    only place the component scores are visible.
                </p>
            </header>

            <!-- Same tab pattern as the verification queue and settings. -->
            <nav class="rv-tabs" aria-label="Match status">
                <Link
                    v-for="tab in tabs"
                    :key="tab.key"
                    :href="`/admin/matches${tab.key ? `?status=${tab.key}` : ''}`"
                    class="rv-tab"
                    :class="{ 'is-active': filters.status === tab.key }"
                    :aria-current="filters.status === tab.key ? 'page' : undefined"
                >
                    <span>{{ tab.label }}</span>
                    <span class="rv-tab__count">{{ counts[tab.count] }}</span>
                </Link>
            </nav>

            <div class="rv-actions">
                <button type="button" class="rv-btn rv-btn--ghost" @click="sortBy('score')">
                    Sort by score
                </button>
                <button type="button" class="rv-btn rv-btn--ghost" @click="sortBy('date')">
                    Sort by date
                </button>
            </div>

            <BaseCard v-if="!matches.data.length">
                <p class="rv-body" style="margin: 0">
                    Nothing here yet. Introductions are generated when a profile is approved.
                </p>
            </BaseCard>

            <div v-else class="rv-stack" style="gap: 12px">
                <Link
                    v-for="match in matches.data"
                    :key="match.id"
                    :href="`/admin/matches/${match.id}`"
                    class="rv-row"
                >
                    <span class="rv-row__score">{{ Math.round(match.score) }}</span>

                    <span class="rv-stack" style="gap: 2px">
                        <span class="rv-strong">{{ match.seeker }} → {{ match.matched }}</span>
                        <span class="rv-small">Generated {{ match.generatedAt }}</span>
                    </span>

                    <span class="rv-match__lozenge">{{ match.status }}</span>
                    <RvIcon name="arrow-right" :size="16" />
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-row {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 16px 24px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
    color: var(--ink);
    text-decoration: none;
}

.rv-row:hover { border-color: var(--brass); }

.rv-row > .rv-stack { flex: 1; min-width: 0; }

.rv-row__score {
    font-family: var(--display);
    font-size: var(--fs-display-3);
    line-height: 1;
    color: var(--oxblood);
    font-variant-numeric: tabular-nums;
    min-width: 48px;
}
</style>
