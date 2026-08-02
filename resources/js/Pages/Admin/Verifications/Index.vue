<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import RvIcon from '@/Components/RvIcon.vue';

defineProps({
    verifications: { type: Object, required: true },
    status: { type: String, required: true },
    counts: { type: Object, required: true },
    openReports: { type: Number, default: 0 },
});

const page = usePage();

const FILTERS = [
    { key: 'pending', label: 'Waiting' },
    { key: 'verified', label: 'Verified' },
    { key: 'rejected', label: 'Sent back' },
];
</script>

<template>
    <AppLayout title="Verification queue">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Verification queue</h1>
                <p class="rv-lead" style="max-width: 56ch">
                    {{ counts.pending }} {{ counts.pending === 1 ? 'profile is' : 'profiles are' }} waiting.
                    Oldest first — whoever has waited longest is at the top.
                </p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <nav class="rv-tabs" aria-label="Filter by status">
                <Link
                    v-for="filter in FILTERS"
                    :key="filter.key"
                    :href="`/admin/verifications?status=${filter.key}`"
                    class="rv-tab"
                    :class="{ 'is-active': filter.key === status }"
                    :aria-current="filter.key === status ? 'page' : undefined"
                >
                    <span>{{ filter.label }}</span>
                    <span class="rv-tab__count">{{ counts[filter.key] ?? 0 }}</span>
                </Link>

                <!--
                    Reports sit here rather than in the tab bar. It is the same
                    job — deciding whether somebody belongs here — and the bar
                    is four tabs, never five.
                -->
                <Link href="/admin/reports" class="rv-tab">
                    <span>Reports</span>
                    <span v-if="openReports > 0" class="rv-tab__count">{{ openReports }}</span>
                    <span v-else class="rv-tab__count rv-tab__count--done">—</span>
                </Link>
            </nav>

            <div v-if="verifications.data.length" class="rv-stack" style="gap: 12px">
                <Link
                    v-for="item in verifications.data"
                    :key="item.id"
                    :href="`/admin/verifications/${item.id}`"
                    class="rv-queue__row"
                >
                    <span class="rv-queue__thumb">
                        <img v-if="item.thumbnail" :src="item.thumbnail" alt="" width="72" height="90" loading="lazy" />
                        <RvIcon v-else name="profile" :size="22" />
                    </span>

                    <span class="rv-queue__who">
                        <span class="rv-queue__name">{{ item.name }}<template v-if="item.age">, {{ item.age }}</template></span>
                        <span class="rv-small">{{ item.city || 'No location given' }}</span>
                    </span>

                    <span class="rv-queue__meta">
                        <span class="rv-pill">{{ item.photoCount }} of 4 photos</span>
                        <span v-if="item.attempt > 1" class="rv-pill">Attempt {{ item.attempt }}</span>
                        <span class="rv-small">{{ item.waitingSince }}</span>
                    </span>
                </Link>
            </div>

            <BaseCard v-else quiet>
                <p class="rv-body" style="text-align: center">
                    Nothing here. Every profile that has been submitted has already been looked at.
                </p>
            </BaseCard>

            <nav v-if="verifications.links.length > 3" class="rv-queue__pages" aria-label="Pages">
                <Link
                    v-for="link in verifications.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    class="rv-queue__page"
                    :class="{ 'is-active': link.active, 'is-dead': !link.url }"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-queue__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px 24px;
    padding: 16px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
    text-decoration: none;
    color: var(--ink);
    transition:
        transform var(--dur-tap) var(--ease),
        border-color var(--dur-tap) var(--ease);
}

.rv-queue__row:hover {
    transform: translateY(-2px);
    border-color: var(--brass-bright);
    color: var(--ink);
}

.rv-queue__thumb {
    position: relative;
    overflow: hidden;
    flex: 0 0 auto;
    width: 56px;
    height: 70px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: linear-gradient(160deg, var(--oxblood) 0%, var(--oxblood-deep) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(185, 145, 84, 0.5);
}

.rv-queue__thumb img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.rv-queue__who {
    flex: 1 1 200px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.rv-queue__name {
    font-family: var(--display);
    font-size: var(--fs-display-3);
    line-height: 1.2;
    color: var(--ink);
}

.rv-queue__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 12px;
}

.rv-queue__pages {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rv-queue__page {
    min-width: var(--tap-min);
    min-height: var(--tap-min);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 12px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    text-decoration: none;
    color: var(--ink-soft);
    font-size: var(--fs-body-3);
}

.rv-queue__page.is-active {
    background: var(--brass);
    border-color: var(--brass);
    color: var(--oxblood-deep);
}

.rv-queue__page.is-dead {
    opacity: 0.4;
    pointer-events: none;
}
</style>
