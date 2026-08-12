<script setup>
import { haptic } from '@/haptics';
import { computed, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import MatchCard from '@/Components/MatchCard.vue';
import RvIcon from '@/Components/RvIcon.vue';

const props = defineProps({
    completeness: { type: Number, default: 0 },
    firstName: { type: String, default: null },
    credits: { type: Number, default: 0 },
    verification: { type: Object, required: true },
    matches: { type: Array, default: () => [] },
    lockedCount: { type: Number, default: 0 },
    openCount: { type: Number, default: 0 },
    unsealed: { type: Array, default: () => [] },
});

const page = usePage();

/**
 * Five states, decided once here rather than as a pile of v-ifs in the
 * template. Each one is designed — a marriage platform that renders a blank
 * panel and calls it "no results" has failed the person looking at it.
 */
const state = computed(() => {
    if (props.verification.status !== 'verified') return 'unverified';
    if (props.matches.length === 0) return 'searching';
    if (props.credits === 0 && props.lockedCount > 0) return 'needs-credits';
    if (props.lockedCount === 0) return 'all-open';
    return 'has-credits';
});

// ── The unsealing ────────────────────────────────────────────────────────
//
// Cards unseal one at a time, in sequence, never all at once.
//
// ⚠️ Presentation only. The credits are already spent, the ledger rows are
// already written and the statuses are already unlocked before any of this
// begins. Closing the tab mid-ceremony costs the member nothing — they land
// on open cards next time.

const unsealingId = ref(null);

/*
 * ⚠️ The unsealing announces itself ONCE, at the end.
 *
 * Narrating each card would flood a screen reader with the same sentence
 * three times while the ceremony it is describing is purely visual. One
 * polite announcement, after everything has landed.
 */
const announcement = ref('');
const ceremonyRunning = ref(false);
const finished = ref(false);
const skipped = ref(false);

const reduceMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

async function runCeremony(ids) {
    if (!ids.length) return;

    ceremonyRunning.value = true;

    // With motion off this is a 200ms cross-fade and the copy still arrives.
    const perCard = reduceMotion() ? 200 : 900 + 70 * 3;

    for (const id of ids) {
        if (skipped.value) break;
        unsealingId.value = id;
        await new Promise((resolve) => setTimeout(resolve, perCard));
    }

    unsealingId.value = null;
    ceremonyRunning.value = false;
    finished.value = true;

    announcement.value = ids.length === 1
        ? '1 introduction unlocked'
        : `${ids.length} introductions unlocked`;

    // ⚠️ The unseal. One of exactly three sanctioned haptic moments.
    haptic('unseal');
}

onMounted(() => {
    if (props.unsealed?.length) runCeremony(props.unsealed);
});

function unlock(id) {
    router.post(`/matches/${id}/unlock`, {}, { preserveScroll: true });
}

const heading = computed(() => {
    if (props.lockedCount === 1) return 'One person is waiting to meet you.';
    if (props.lockedCount > 1) return `${props.lockedCount} people are waiting to meet you.`;
    return 'Your introductions.';
});
</script>

<template>
    <AppLayout title="Matches">
        <!-- Announced once, when the ceremony finishes. -->
        <p class="rv-sr-only" role="status" aria-live="polite">{{ announcement }}</p>

        <div class="rv-shell rv-section--app" data-pull-refresh>
            <div class="rv-dash">
                <!-- ── Main column ──────────────────────────────────────── -->
                <div class="rv-stack" style="gap: 32px">
                    <header class="rv-stack" style="gap: 12px">
                        <span class="rv-label">{{ firstName ? `Hello, ${firstName}` : 'Welcome' }}</span>
                        <h1 class="rv-d2">{{ heading }}</h1>
                        <span class="rv-underline" aria-hidden="true" />
                    </header>

                    <p v-if="page.props.flash?.error" class="rv-flash rv-flash--error" role="alert">
                        {{ page.props.flash.error }}
                    </p>

                    <!-- 1. Not yet verified ─────────────────────────────── -->
                    <BaseCard v-if="state === 'unverified'" accent>
                        <div class="rv-stack" style="gap: 16px">
                            <span class="rv-label rv-label--brass">Verification</span>

                            <template v-if="['pending', 'review'].includes(verification.status)">
                                <h2 class="rv-d3">{{ $t('verification.pending_heading') }}</h2>
                                <p class="rv-body">
                                    {{ $t('verification.pending_body') }}
                                </p>
                            </template>

                            <template v-else-if="verification.status === 'rejected'">
                                <h2 class="rv-d3">{{ $t('verification.rejected_heading') }}</h2>
                                <p class="rv-body">{{ verification.rejectionReason }}</p>
                                <BaseButton href="/profile/photos" variant="primary">
                                    {{ $t('dashboard.update_your_photos') }}
                                </BaseButton>
                            </template>

                            <template v-else>
                                <h2 class="rv-d3">{{ $t('verification.incomplete_heading') }}</h2>
                                <p class="rv-body">
                                    You are {{ completeness }}% of the way there. Once it is complete and
                                    verified, we start looking.
                                </p>
                                <BaseButton href="/profile" variant="primary">Continue</BaseButton>
                            </template>
                        </div>
                    </BaseCard>

                    <!-- 2. Verified, nothing above the threshold yet ────── -->
                    <BaseCard v-else-if="state === 'searching'" accent>
                        <div class="rv-stack" style="gap: 16px">
                            <span class="rv-label rv-label--brass">Still looking</span>
                            <h2 class="rv-d3">{{ $t('dashboard.searching_heading') }}</h2>
                            <p class="rv-body">
                                {{ $t('dashboard.searching_body') }}
                            </p>
                        </div>
                    </BaseCard>

                    <!-- 3, 4, 5. The cards ─────────────────────────────── -->
                    <template v-else>
                        <BaseCard v-if="lockedCount > 0" accent>
                            <div class="rv-stack" style="gap: 12px">
                                <p class="rv-body" style="margin: 0">
                                    {{ $t('dashboard.chosen') }}
                                </p>
                                <p class="rv-small" style="margin: 0">
                                    {{ $t('dashboard.no_browsing') }}
                                </p>
                            </div>
                        </BaseCard>

                        <div class="rv-matches">
                            <MatchCard
                                v-for="match in matches"
                                :key="match.id"
                                class="rv-arrive"
                                :match="match"
                                :unsealing="unsealingId === match.id"
                                :dimmed="ceremonyRunning && unsealingId !== match.id"
                                @unlock="unlock"
                            />
                        </div>

                        <p v-if="finished" class="rv-d3 rv-arrive" style="text-align: center">
                            {{ $t('dashboard.unsealed') }}
                        </p>

                        <div v-if="ceremonyRunning && !skipped" style="text-align: center">
                            <BaseButton variant="ghost" @click="skipped = true">Skip</BaseButton>
                        </div>

                        <BaseCard v-if="state === 'all-open' && openCount > 0">
                            <div class="rv-stack" style="gap: 12px">
                                <h2 class="rv-d3">{{ $t('dashboard.all_open_heading') }}</h2>
                                <p class="rv-body">
                                    {{ $t('dashboard.all_open_body') }}
                                </p>
                                <BaseButton href="/packages" variant="secondary">See packages</BaseButton>
                            </div>
                        </BaseCard>
                    </template>
                </div>

                <!-- ── Right rail — a real second column at desktop ─────── -->
                <aside class="rv-dash__rail rv-stack" style="gap: 24px">
                    <div class="rv-rail-card">
                        <span class="rv-label">{{ $t('dashboard.credits_label') }}</span>
                        <span class="rv-balance">{{ credits }}</span>
                        <p v-if="credits === 0" class="rv-small" style="margin: 0">
                            {{ $t('dashboard.ready') }}
                        </p>
                        <BaseButton href="/packages" :variant="credits === 0 ? 'primary' : 'secondary'">
                            {{ credits === 0 ? 'Open an introduction' : 'Add more' }}
                        </BaseButton>
                    </div>

                    <div class="rv-rail-card">
                        <span class="rv-label">Your profile</span>
                        <p class="rv-small" style="margin: 0">{{ completeness }}% complete</p>
                        <BaseButton href="/purchases" variant="ghost">{{ $t('purchases.heading') }}</BaseButton>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Contextual, and never shown when it would do nothing. -->
        <Link v-if="state === 'needs-credits'" href="/packages" class="rv-fab rv-pressable">
            <RvIcon name="lock" :size="18" />
            <span>Open</span>
        </Link>
    </AppLayout>
</template>
