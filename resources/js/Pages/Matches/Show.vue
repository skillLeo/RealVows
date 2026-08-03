<script setup>
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import CompatibilityRing from '@/Components/CompatibilityRing.vue';
import PhotoGallery from '@/Components/PhotoGallery.vue';
import ReportSheet from '@/Components/ReportSheet.vue';
import RvIcon from '@/Components/RvIcon.vue';

/**
 * ⚠️ THE PAYOFF SCREEN. This is what the member actually paid for, and
 * everything before it is anticipation.
 *
 * A teaser never reaches here — the controller answers 404 rather than
 * showing a paywall, because a paywall confirms the person exists and
 * invites somebody to try the next id.
 */
const props = defineProps({
    match: { type: Object, required: true },
});

const fullName = computed(() =>
    [props.match.firstName, props.match.lastName].filter(Boolean).join(' ')
);

const meta = computed(() => [props.match.age, props.match.city].filter(Boolean).join(', '));

// ── The bio, expanded in place rather than by navigating away ────────────
const LONG = 420;
const bioExpanded = ref(false);
const bioIsLong = computed(() => (props.match.bio?.length ?? 0) > LONG);
const bioShown = computed(() =>
    !bioIsLong.value || bioExpanded.value
        ? props.match.bio
        : `${props.match.bio.slice(0, LONG).trimEnd()}…`
);

/*
 * Phase 2's only action. Express interest and Pass are Phase 3 and are
 * deliberately NOT rendered as disabled buttons — a greyed-out control that
 * does nothing reads as broken software to a non-technical owner.
 */
const reportOpen = ref(false);
</script>

<template>
    <AppLayout :title="match.firstName">
        <div class="rv-payoff">
            <!-- ── The face arrives first, and alone ────────────────────── -->
            <div class="rv-payoff__media">
                <PhotoGallery :photos="match.photos" :name="match.firstName">
                    <template #overlay>
                        <div class="rv-identity">
                            <h1 class="rv-identity__name">{{ fullName }}</h1>
                            <p class="rv-identity__meta">
                                <span>{{ meta }}</span>
                                <span v-if="match.verified" class="rv-identity__verified">
                                    <RvIcon name="shield" :size="14" />
                                    Verified
                                </span>
                            </p>
                        </div>
                    </template>
                </PhotoGallery>
            </div>

            <!-- ── Why them, then who they are ─────────────────────────── -->
            <div class="rv-payoff__body">
                <BaseCard accent>
                    <div class="rv-stack" style="gap: 24px">
                        <div class="rv-payoff__score">
                            <CompatibilityRing
                                :score="match.score"
                                :animate="match.firstView"
                                label="Compatibility score"
                            />
                            <span class="rv-label rv-label--brass">Compatibility</span>
                        </div>

                        <p v-if="match.rationale" class="rv-lead" style="margin: 0">
                            {{ match.rationale }}
                        </p>

                        <ul v-if="match.commonalities?.length" class="rv-common">
                            <li
                                v-for="(line, i) in match.commonalities"
                                :key="line"
                                class="rv-arrive"
                                :style="{ animationDelay: `${i * 70}ms` }"
                            >
                                {{ line }}
                            </li>
                        </ul>

                        <p v-if="match.unlockedAt" class="rv-small" style="margin: 0">
                            Introduced on {{ match.unlockedAt }}
                        </p>
                    </div>
                </BaseCard>

                <BaseCard v-if="match.bio">
                    <div class="rv-stack" style="gap: 12px">
                        <span class="rv-label">{{ $t('match.in_their_words') }}</span>
                        <span class="rv-underline" aria-hidden="true" />
                        <p class="rv-body rv-bio">{{ bioShown }}</p>
                        <button
                            v-if="bioIsLong"
                            type="button"
                            class="rv-readmore"
                            @click="bioExpanded = !bioExpanded"
                        >
                            {{ bioExpanded ? 'Show less' : 'Read more' }}
                        </button>
                    </div>
                </BaseCard>

                <!-- The profile, in the same order as the builder. A section
                     with nothing in it never arrives from the server, so
                     there is no empty label to render. -->
                <BaseCard v-for="section in match.sections" :key="section.heading">
                    <div class="rv-stack" style="gap: 16px">
                        <span class="rv-label">{{ section.heading }}</span>
                        <span class="rv-underline" aria-hidden="true" />
                        <dl class="rv-facts">
                            <template v-for="(value, label) in section.rows" :key="label">
                                <dt>{{ label }}</dt>
                                <dd>{{ value }}</dd>
                            </template>
                        </dl>
                    </div>
                </BaseCard>

                <div class="rv-payoff__quiet">
                    <BaseButton variant="ghost" @click="reportOpen = true">
                        {{ $t('report.heading') }}
                    </BaseButton>
                </div>
            </div>
        </div>

        <ReportSheet v-model:open="reportOpen" :match-id="match.id" :name="match.firstName" />
    </AppLayout>
</template>

<style scoped>
.rv-payoff {
    display: grid;
    gap: 0;
    grid-template-columns: 1fr;
}

.rv-payoff__body {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding: 32px 20px calc(96px + env(safe-area-inset-bottom));
    max-width: 720px;
    width: 100%;
    margin: 0 auto;
}

/* Tablet and up: the face and identity stay put, the profile scrolls past. */
@media (min-width: 768px) {
    .rv-payoff {
        grid-template-columns: 380px minmax(0, 1fr);
        gap: 40px;
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 24px;
        align-items: start;
    }

    .rv-payoff__media {
        position: sticky;
        top: 88px;
        padding-top: 32px;
    }

    .rv-payoff__body {
        padding: 32px 0 96px;
        margin: 0;
    }
}

@media (min-width: 1024px) {
    .rv-payoff {
        grid-template-columns: 480px minmax(0, 1fr);
    }
}

/* Identity over a scrim, so it stays legible on any photograph. */
.rv-identity {
    padding: 56px 24px 24px;
    background: linear-gradient(to top, var(--oxblood-deep) 12%, rgba(74, 18, 32, 0) 100%);
    color: var(--cream);
}

.rv-identity__name {
    margin: 0;
    font-family: var(--display);
    font-size: var(--fs-display-2);
    font-weight: 400;
    line-height: 1.15;
    letter-spacing: -0.01em;
}

.rv-identity__meta {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px;
    margin: 8px 0 0;
    font-size: var(--fs-body-2);
    color: var(--on-dark);
}

.rv-identity__verified {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: var(--brass-bright);
}

/* The number is the thing. "Compatibility" is said once, quietly, beneath. */
.rv-payoff__score {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
}

.rv-common {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin: 0;
    padding: 0;
    list-style: none;
    font-size: var(--fs-body-2);
    color: var(--ink);
}

.rv-common li {
    position: relative;
    padding-left: 22px;
}

.rv-common li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 13px;
    width: 12px;
    height: 1px;
    background: var(--brass);
}

.rv-bio {
    margin: 0;
    white-space: pre-line;
    max-width: 66ch;
}

.rv-readmore {
    align-self: flex-start;
    padding: 0;
    border: 0;
    background: none;
    color: var(--brass-ink);
    font-size: var(--fs-body-3);
    font-weight: 500;
    text-decoration: underline;
    text-underline-offset: 3px;
    cursor: pointer;
    min-height: var(--tap-min);
}

.rv-facts {
    display: grid;
    grid-template-columns: minmax(0, auto) minmax(0, 1fr);
    gap: 12px 24px;
    margin: 0;
}

.rv-facts dt {
    font-size: var(--fs-body-3);
    color: var(--ink-soft);
}

.rv-facts dd {
    margin: 0;
    font-size: var(--fs-body-2);
    color: var(--ink);
}

.rv-payoff__quiet {
    display: flex;
    justify-content: center;
    padding-top: 8px;
}
</style>
