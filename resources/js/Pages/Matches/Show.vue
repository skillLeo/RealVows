<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import CompatibilityRing from '@/Components/CompatibilityRing.vue';
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

/*
 * ⚠️ THE LETTER STANDS WHERE THE PHOTOGRAPH DID. Not an avatar — nothing is
 * missing here. The pictures are with the trusted contact by design, and an
 * initial in the product's own arch says that far better than a grey
 * silhouette apologising for an image that was never coming.
 */
const initial = computed(() => (props.match.firstName ?? '?').trim().charAt(0).toUpperCase());

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

/*
 * ⚠️ NOT OPTIMISTIC, AND THIS IS THE SECOND EXCEPTION TO OPTIMISTIC UI AFTER
 * THE CREDIT BALANCE.
 *
 * A response can reveal a phone number and can notify another person. Showing
 * a member "you are interested" before the server has agreed risks showing
 * them a mutual match that did not happen, or a state the server rejected.
 * The button disables, the request goes, the server re-renders.
 */
const responding = ref(false);

const respond = (response) => {
    if (responding.value) return;
    responding.value = true;

    router.post(`/matches/${props.match.id}/respond`, { response }, {
        onFinish: () => { responding.value = false; },
    });
};

const block = () => {
    if (responding.value) return;
    responding.value = true;

    router.post(`/matches/${props.match.id}/block`, {}, {
        onFinish: () => { responding.value = false; },
    });
};
</script>

<template>
    <AppLayout :title="match.firstName">
        <div class="rv-payoff">
            <!-- ── THE NAME ARRIVES FIRST, AND ALONE ──────────────────────
                 This was a full-bleed photo gallery with the name laid over
                 it. There are no photographs for a member any more — they are
                 with the trusted contact — so a gallery here rendered an empty
                 frame with a name floating on nothing.

                 What is left is the thing that was always the point: a name, in
                 the display face, at the size the photograph used to be. Set
                 against the arch rather than in front of a picture. -->
            <div class="rv-payoff__media">
                <div class="rv-portal">
                    <span class="rv-portal__letter" aria-hidden="true">{{ initial }}</span>

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

                    <p v-if="match.photographsWithheld" class="rv-portal__note">
                        {{ $t('match.photos_with_contact') }}
                    </p>
                </div>
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

                <!--
                    ⚠️ THE DECISION. Both answers are final and the copy says so
                    before either is pressed, not after — a confirmation that
                    appears only once somebody has already clicked is a warning
                    delivered too late to be one.

                    There is no undo. An undo turns a considered decision into a
                    swipe, and the other person may already have been told.
                -->
                <BaseCard v-if="match.interest && match.interest.canRespond">
                    <h2 class="rv-display rv-display--sm">{{ $t('respond.heading') }}</h2>

                    <div class="rv-respond__actions">
                        <BaseButton :disabled="responding" @click="respond('interested')">
                            {{ $t('respond.interested') }}
                        </BaseButton>
                        <BaseButton variant="secondary" :disabled="responding" @click="respond('passed')">
                            {{ $t('respond.pass') }}
                        </BaseButton>
                    </div>

                    <p class="rv-quiet">{{ $t('respond.final') }}</p>
                </BaseCard>

                <!-- Already answered: state it plainly rather than showing nothing. -->
                <BaseCard v-else-if="match.interest && match.interest.response">
                    <p v-if="match.interest.mutual">{{ $t('respond.mutual_state') }}</p>
                    <p v-else-if="match.interest.response === 'interested'">{{ $t('respond.interested_state') }}</p>
                    <p v-else>{{ $t('respond.passed_state') }}</p>
                </BaseCard>

                <!--
                    ⚠️ CONTACT DETAILS ARE ABSENT FROM THE PAYLOAD UNTIL MUTUAL
                    INTEREST — not null, absent. So this block cannot render
                    early even if somebody removes the condition.
                -->
                <BaseCard v-if="match.contact">
                    <h2 class="rv-display rv-display--sm">{{ $t('respond.contact_heading') }}</h2>
                    <dl class="rv-facts">
                        <template v-if="match.contact.email">
                            <dt>Email</dt>
                            <dd><a :href="`mailto:${match.contact.email}`">{{ match.contact.email }}</a></dd>
                        </template>
                        <template v-if="match.contact.phone">
                            <dt>Phone</dt>
                            <dd><a :href="`tel:${match.contact.phone.replace(/\\s+/g, '')}`">{{ match.contact.phone }}</a></dd>
                        </template>
                    </dl>
                </BaseCard>

                <div class="rv-payoff__quiet">
                    <BaseButton variant="ghost" @click="reportOpen = true">
                        {{ $t('report.heading') }}
                    </BaseButton>
                    <BaseButton variant="ghost" :disabled="responding" @click="block">
                        {{ $t('respond.block') }}
                    </BaseButton>
                </div>
            </div>
        </div>

        <ReportSheet v-model:open="reportOpen" :match-id="match.id" :name="match.firstName" />
    </AppLayout>
</template>

<style scoped>

/* ── The portal ──────────────────────────────────────────────────────────
   Where the photo gallery was. An arch, their initial, and their name at the
   scale a face used to occupy — so the top of the page still has weight
   rather than a hole where an image failed to arrive. */
.rv-portal {
    position: relative;
    display: grid;
    place-items: center;
    align-content: center;
    gap: 18px;
    min-height: clamp(280px, 42vh, 420px);
    padding: 40px 28px;
    text-align: center;
    border-radius: var(--r);
    border: 1px solid color-mix(in oklab, var(--brass) 34%, transparent);
    /* Warm at the top, settling into the card below — the same light the home
       page hero uses, and the only depth here since shadows are banned. */
    background:
        radial-gradient(120% 80% at 50% 0%, color-mix(in oklab, var(--brass) 16%, transparent), transparent 70%),
        var(--card);
}

/* ⚠️ HUGE AND QUIET AT ONCE. Large enough to hold the space a photograph
   held, faint enough that the NAME is what is read first — it is the letter's
   job to fill the frame, not to be the subject. */
.rv-portal__letter {
    font-family: var(--display);
    font-size: clamp(120px, 22vw, 200px);
    line-height: .8;
    color: color-mix(in oklab, var(--brass) 30%, transparent);
    user-select: none;
}

.rv-portal__note {
    max-width: 42ch;
    font-size: 13px;
    line-height: 1.5;
    color: var(--ink-soft);
}

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
