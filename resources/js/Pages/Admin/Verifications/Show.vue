<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseRadio from '@/Components/BaseRadio.vue';
import SheetModal from '@/Components/SheetModal.vue';
import VerifiedBadge from '@/Components/VerifiedBadge.vue';

const props = defineProps({
    verification: { type: Object, required: true },
    member: { type: Object, required: true },
    photos: { type: Array, required: true },
    reasons: { type: Array, required: true },
});

const rejecting = ref(false);

const form = useForm({
    decision: 'approve',
    reason: props.reasons[0] ?? '',
    note: '',
});

function approve() {
    form.decision = 'approve';
    form.post(`/admin/verifications/${props.verification.id}/review`);
}

function reject() {
    form.decision = 'reject';
    form.post(`/admin/verifications/${props.verification.id}/review`, {
        onSuccess: () => (rejecting.value = false),
    });
}

const decided = props.verification.status === 'verified' || props.verification.status === 'rejected';
</script>

<template>
    <AppLayout :title="`Reviewing ${member.name}`">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <div>
                <Link href="/admin/verifications" class="rv-link-action">← Back to the queue</Link>
            </div>

            <header class="rv-review__head">
                <div class="rv-stack" style="gap: 8px">
                    <h1 class="rv-d2">{{ member.name }}<template v-if="member.age">, {{ member.age }}</template></h1>
                    <p class="rv-body">{{ member.city }} · {{ member.email }}</p>
                </div>

                <VerifiedBadge v-if="verification.status === 'verified'" size="lg" label="Verified" />
                <span v-else-if="verification.status === 'rejected'" class="rv-pill rv-pill--lg">Sent back</span>
                <span v-else class="rv-pill rv-pill--lg">Attempt {{ verification.attempt }}</span>
            </header>

            <!--
                The code sits large and on its own so a reviewer can hold it
                against the handwritten note in the photograph and decide in
                seconds rather than squinting between two small strings.
            -->
            <BaseCard accent>
                <div class="rv-review__codeblock">
                    <div class="rv-stack" style="gap: 8px">
                        <span class="rv-label rv-label--brass">The code we issued</span>
                        <p class="rv-body">It must match the handwritten note in the verification photo, exactly.</p>
                    </div>
                    <span class="rv-review__code">{{ verification.code }}</span>
                </div>
            </BaseCard>

            <!-- Verification photo and the four profile photos, side by side
                 and large enough to actually compare faces. -->
            <div class="rv-review__grid">
                <figure class="rv-review__figure rv-review__figure--selfie">
                    <img
                        v-if="verification.selfieUrl"
                        :src="verification.selfieUrl"
                        alt="Verification photo: the member holding a handwritten code"
                        loading="lazy"
                    />
                    <span v-else class="rv-review__missing">No verification photo uploaded</span>
                    <figcaption class="rv-label rv-label--brass">Verification photo</figcaption>
                </figure>

                <figure v-for="photo in photos" :key="photo.id" class="rv-review__figure">
                    <img :src="photo.url" :alt="`${photo.title} photo`" loading="lazy" />
                    <figcaption class="rv-label">{{ photo.title }}</figcaption>
                </figure>
            </div>

            <BaseCard v-if="decided" quiet>
                <p class="rv-body">
                    Decided by {{ verification.reviewedBy || 'a reviewer' }} on {{ verification.reviewedAt }}.
                    <template v-if="verification.rejectionReason">
                        Reason given: {{ verification.rejectionReason }}
                    </template>
                </p>
            </BaseCard>

            <div v-else class="rv-actions">
                <BaseButton variant="primary" :disabled="form.processing" @click="approve">
                    Approve this member
                </BaseButton>
                <BaseButton variant="ghost" @click="rejecting = true">Send back with guidance</BaseButton>
            </div>
        </div>

        <SheetModal :open="rejecting" title="Send this back" @close="rejecting = false">
            <div class="rv-stack" style="gap: 24px">
                <p class="rv-body">
                    The member is told what to change and can upload a new photo straight away.
                    Choose whichever comes closest.
                </p>

                <div class="rv-stack" style="gap: 4px">
                    <BaseRadio
                        v-for="reason in reasons"
                        :key="reason"
                        v-model="form.reason"
                        name="reason"
                        :value="reason"
                    >
                        {{ reason }}
                    </BaseRadio>
                </div>

                <div class="rv-field">
                    <label for="rv-note" class="rv-label">Anything to add (optional)</label>
                    <textarea id="rv-note" v-model="form.note" class="rv-input rv-textarea" rows="4" />
                    <span class="rv-help">This is shown to the member, so keep it kind.</span>
                </div>

                <div class="rv-actions">
                    <BaseButton variant="primary" :disabled="form.processing" @click="reject">
                        Send it back
                    </BaseButton>
                    <BaseButton variant="secondary" @click="rejecting = false">Cancel</BaseButton>
                </div>
            </div>
        </SheetModal>
    </AppLayout>
</template>

<style scoped>
.rv-review__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px 24px;
}

.rv-review__codeblock {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.rv-review__code {
    font-family: var(--display);
    font-size: var(--fs-display-2);
    line-height: 1;
    letter-spacing: 0.12em;
    color: var(--oxblood);
    padding: 16px 24px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
}

.rv-review__grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
    gap: 24px;
}

.rv-review__figure {
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.rv-review__figure img {
    width: 100%;
    aspect-ratio: 4 / 5;
    object-fit: cover;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--oxblood-deep);
}

.rv-review__figure--selfie img {
    border-color: var(--brass);
}

.rv-review__missing {
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    aspect-ratio: 4 / 5;
    padding: 24px;
    border: 1px dashed var(--hairline);
    border-radius: var(--r);
    color: var(--ink-soft);
    font-size: var(--fs-body-3);
}

.rv-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}
</style>
