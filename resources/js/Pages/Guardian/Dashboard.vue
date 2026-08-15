<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    ward: { type: Object, required: true },
    introductions: { type: Array, default: () => [] },
    isAppointed: { type: Boolean, default: false },
});

const page = usePage();

/*
 * ⚠️ OPTIMISTIC IN APPEARANCE ONLY. THE SERVER REMAINS THE AUTHORITY.
 *
 * The card leaves the moment the trusted contact decides, because waiting for a
 * round trip on a Jamaican 4G connection makes a considered decision feel like
 * a broken button — and the commonest way that goes wrong is a second press.
 *
 * But this is somebody deciding, on another adult's behalf, whether an
 * introduction proceeds. So the optimism is confined to the animation out.
 * `pending` is cleared unconditionally in onFinish, which means the card only
 * STAYS gone if the server came back without it. A refused, failed or
 * abandoned request puts it back, every time, because the list is re-rendered
 * from props rather than from anything held here.
 *
 * ⚠️ WHAT THIS PATTERN MUST NEVER BE APPLIED TO: anything spending credits.
 * A balance that drops optimistically and rolls back has shown somebody a
 * number that was never true about their money. Introductions are recoverable;
 * a wrong balance is a support ticket and a loss of trust.
 */
const settling = ref(new Set());
const failed = ref(null);

const visible = computed(() =>
    props.introductions.filter((item) => !settling.value.has(item.matchId))
);

function decide(matchId, decision) {
    if (settling.value.has(matchId)) return;

    failed.value = null;
    settling.value = new Set(settling.value).add(matchId);

    router.post(
        `/trusted-contact-dashboard/decisions/${matchId}`,
        { decision, gate: 'introduction', reason: '' },
        {
            preserveScroll: true,

            /*
             * ⚠️ Named, not generic. "Something went wrong" leaves somebody
             * unsure whether their decision was recorded, and the safe reading
             * of that ambiguity is to press again.
             */
            onError: () => {
                failed.value = matchId;
            },

            // Runs after success, after failure, and after an exception. The
            // card's absence is therefore never this component's opinion.
            onFinish: () => {
                const next = new Set(settling.value);
                next.delete(matchId);
                settling.value = next;
            },
        }
    );
}
</script>

<template>
    <AppLayout title="Introductions">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <h1 class="rv-d2">
                    {{ $t('guardian_area.dashboard_heading', { name: ward.firstName }) }}
                </h1>
                <p class="rv-lead" style="max-width: 54ch">
                    {{ $t('guardian_area.dashboard_lead') }}
                </p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <!-- ⚠️ ANNOUNCED, NOT ONLY SHOWN. The card vanishing is the only
                 feedback a sighted person needs; somebody on a screen reader
                 gets silence unless it is said. -->
            <p v-if="failed" class="rv-flash rv-flash--error" role="alert">
                {{ $t('guardian_area.decision_failed') }}
            </p>

            <BaseCard v-if="!visible.length">
                <div class="rv-stack" style="gap: 8px">
                    <h2 class="rv-d3">{{ $t('guardian_area.nothing_yet') }}</h2>
                    <p class="rv-body">{{ $t('guardian_area.nothing_yet_body') }}</p>
                </div>
            </BaseCard>

            <BaseCard v-for="item in visible" :key="item.matchId">
                <div class="rv-stack" style="gap: 20px">
                    <div class="rv-stack" style="gap: 4px">
                        <h2 class="rv-d3">{{ item.firstName }}, {{ item.age }}</h2>
                        <p class="rv-help">{{ item.city }}, {{ item.country }}</p>
                    </div>

                    <!-- ⚠️ Photographs appear only for a family trusted
                         contact. The presenter decides; this template never
                         builds a photo URL of its own. -->
                    <div v-if="item.photos?.length" class="rv-grid rv-grid--3">
                        <img
                            v-for="photo in item.photos"
                            :key="photo.id"
                            :src="photo.url"
                            alt=""
                            class="rv-guardian__photo"
                        >
                    </div>

                    <p v-else-if="item.photographsWithheld" class="rv-help">
                        {{ $t('guardian_area.withheld') }}
                    </p>

                    <p v-if="item.about" class="rv-body">{{ item.about }}</p>

                    <!--
                        ⚠️ THE DETAILS ARRIVE HERE ONCE HE HAS APPROVED, BECAUSE
                        HE IS THE ONE MAKING THE INTRODUCTION.

                        The client's scholar ruled that approving an exchange is
                        not the same as being present for it, except where the
                        guardian is unjust, not Muslim, or has died. Outside
                        those, the platform stops being the channel and he
                        becomes it.

                        The member's own screen no longer shows a phone number
                        and tells her he holds it. If this block were missing,
                        that sentence would point at nothing: she cannot proceed,
                        he has nothing to pass on, and the introduction simply
                        ends at the last gate with nobody holding anything.
                    -->
                    <div v-if="item.contactIsYoursToApproach" class="rv-card rv-card--accent rv-stack" style="gap: 8px">
                        <span class="rv-label rv-label--brass">{{ $t('guardian_area.contact_heading') }}</span>
                        <p class="rv-help">{{ $t('guardian_area.contact_help') }}</p>
                        <dl class="rv-facts">
                            <template v-if="item.contact.lastName">
                                <dt>Family name</dt>
                                <dd>{{ item.contact.lastName }}</dd>
                            </template>
                            <template v-if="item.contact.phone">
                                <dt>Phone</dt>
                                <dd><a :href="`tel:${item.contact.phone.replace(/\s+/g, '')}`">{{ item.contact.phone }}</a></dd>
                            </template>
                            <template v-if="item.contact.email">
                                <dt>Email</dt>
                                <dd><a :href="`mailto:${item.contact.email}`">{{ item.contact.email }}</a></dd>
                            </template>
                        </dl>
                    </div>

                    <!-- ⚠️ NO :disabled TIED TO A SHARED FORM. One useForm
                         instance was serving every card, so deciding on one
                         introduction greyed out the buttons on all of them —
                         which reads as the page having frozen. Each decision
                         now guards only its own card, and that card has already
                         left the list by the time it would matter. -->
                    <div class="rv-actions">
                        <BaseButton
                            variant="primary"
                            @click="decide(item.matchId, 'approved')"
                        >
                            {{ $t('guardian_area.approve') }}
                        </BaseButton>
                        <BaseButton
                            variant="secondary"
                            @click="decide(item.matchId, 'declined')"
                        >
                            {{ $t('guardian_area.decline') }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-guardian__photo {
    width: 100%;
    aspect-ratio: 3 / 4;
    object-fit: cover;
    border-radius: var(--r);
}
</style>
