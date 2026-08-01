<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import ProgressRing from '@/Components/ProgressRing.vue';
import VerifiedBadge from '@/Components/VerifiedBadge.vue';

const props = defineProps({
    completeness: { type: Number, required: true },
    firstName: { type: String, default: null },
    credits: { type: Number, required: true },
    verification: { type: Object, required: true },
});

/**
 * Never "instantly" — background work on shared hosting is batched roughly
 * every minute, and verification is checked by a person within a day.
 */
const verificationCopy = computed(
    () =>
        ({
            not_started: {
                label: 'Not started',
                body: 'Finish your profile and upload your photos to be reviewed.',
            },
            pending: {
                label: 'In review',
                body: 'A real person is reviewing your profile — usually within a day.',
            },
            review: {
                label: 'In review',
                body: 'A real person is reviewing your profile — usually within a day.',
            },
            verified: {
                label: 'Verified',
                body: 'Checked by a person, not a machine.',
            },
            rejected: {
                label: 'Needs another try',
                body: props.verification.rejectionReason || 'Please upload a new verification photo.',
            },
        })[props.verification.status] ?? { label: 'Not started', body: '' }
);

const isVerified = computed(() => props.verification.status === 'verified');
</script>

<template>
    <AppLayout title="Your matches">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 48px">
            <header class="rv-stack" style="gap: 8px">
                <span class="rv-label">{{ firstName ? `Welcome back, ${firstName}` : 'Welcome' }}</span>
            </header>

            <!-- Three status cards -->
            <div class="rv-grid rv-grid--3">
                <BaseCard>
                    <div class="rv-stack" style="gap: 24px">
                        <span class="rv-label">Profile completeness</span>
                        <div class="rv-dash__ring">
                            <ProgressRing :percent="completeness" />
                            <div class="rv-stack" style="gap: 6px">
                                <span class="rv-dash__strong">Profile {{ completeness }}% complete</span>
                                <span class="rv-small">
                                    {{ completeness === 100 ? 'Everything is filled in' : 'Pick up where you left off' }}
                                </span>
                            </div>
                        </div>
                        <Link href="/profile" class="rv-link-action">
                            {{ completeness === 100 ? 'Review your profile' : 'Finish your profile' }}
                        </Link>
                    </div>
                </BaseCard>

                <BaseCard>
                    <div class="rv-stack" style="gap: 24px">
                        <span class="rv-label">Verification status</span>
                        <div class="rv-stack" style="gap: 16px; align-items: flex-start">
                            <VerifiedBadge v-if="isVerified" size="lg" label="Verified" />
                            <span v-else class="rv-pill rv-pill--lg">{{ verificationCopy.label }}</span>

                            <span v-if="verification.approvedAt" class="rv-small">
                                Approved {{ verification.approvedAt }}
                            </span>
                        </div>
                        <p class="rv-small">{{ verificationCopy.body }}</p>
                    </div>
                </BaseCard>

                <BaseCard>
                    <div class="rv-stack" style="gap: 20px">
                        <span class="rv-label">Match credits</span>
                        <div class="rv-dash__credits">
                            <span class="rv-numeral rv-dash__count">{{ credits }}</span>
                            <span class="rv-body">matches remaining</span>
                        </div>
                        <p class="rv-small">One-time packages. Nothing recurring, ever.</p>
                    </div>
                </BaseCard>
            </div>

            <!--
                Phase 1 empty state. No match cards and no locked teaser cards
                — those arrive in Phase 2. A blank panel here would be the
                least welcoming thing a new member could meet, so this is
                designed rather than absent.
            -->
            <section class="rv-dash__empty">
                <div class="rv-stack rv-dash__empty-inner" style="gap: 24px">
                    <span class="rv-label rv-label--brass">Your matches</span>
                    <h1 class="rv-d2">Your matches will appear here.</h1>
                    <p class="rv-lead" style="max-width: 52ch">
                        Once your profile is complete and a real person has verified you, RealVows
                        chooses the people you're most compatible with and brings them to you.
                        There is nothing to search through — that is not how this works.
                    </p>
                    <div>
                        <BaseButton href="/profile" variant="primary">
                            {{ completeness === 100 ? 'Review your profile' : 'Continue your profile' }}
                        </BaseButton>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-dash__ring {
    display: flex;
    align-items: center;
    gap: 20px;
}

.rv-dash__strong {
    font-size: var(--fs-body-2);
    font-weight: 500;
}

.rv-dash__credits {
    display: flex;
    align-items: baseline;
    gap: 14px;
}

.rv-dash__count {
    font-size: var(--fs-display-1);
    letter-spacing: -0.015em;
}

.rv-dash__empty {
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 4%, var(--cream));
    padding: clamp(32px, 6vw, 64px) var(--pad-card);
    display: flex;
    justify-content: center;
    text-align: center;
}

.rv-dash__empty-inner {
    align-items: center;
    max-width: 640px;
}
</style>
