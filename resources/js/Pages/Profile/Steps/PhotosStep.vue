<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import PhotoUploadSlot from '@/Components/PhotoUploadSlot.vue';
import RvIcon from '@/Components/RvIcon.vue';
import StepShell from './StepShell.vue';

const props = defineProps({
    photos: { type: Object, required: true },
    verification: { type: Object, required: true },
});

const emit = defineEmits(['updated']);

const SLOTS = [
    { key: 'passport', index: 1, title: 'Passport style', help: 'Clear face, front facing' },
    { key: 'full_length', index: 2, title: 'Full length', help: 'Any setting, however you like' },
    { key: 'social', index: 3, title: 'Social', help: 'With friends or family' },
    { key: 'open', index: 4, title: 'Your choice', help: 'Anything that shows who you are' },
];

const previews = ref({ ...props.photos });
const busy = ref(null);
const problem = ref(null);
const selfieInput = ref(null);

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function upload(slotKey, file) {
    if (!file) return;

    busy.value = slotKey;
    problem.value = null;

    const body = new FormData();
    body.append('photo', file);
    body.append('slot', slotKey);

    try {
        const response = await fetch('/photos', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body,
        });

        const payload = await response.json();

        if (!response.ok) {
            problem.value = payload.message || payload.errors?.photo?.[0] || 'That upload did not go through.';
            return;
        }

        previews.value = { ...previews.value, [slotKey]: payload.photo };
        emit('updated', payload);
    } catch {
        problem.value = 'We could not reach the server. Please try that photo again.';
    } finally {
        busy.value = null;
    }
}

async function remove(slotKey) {
    const photo = previews.value[slotKey];
    if (!photo) return;

    busy.value = slotKey;

    try {
        const response = await fetch(`/photos/${photo.id}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            credentials: 'same-origin',
        });

        if (response.ok) {
            const payload = await response.json();
            const next = { ...previews.value };
            delete next[slotKey];
            previews.value = next;
            emit('updated', payload);
        }
    } finally {
        busy.value = null;
    }
}

function uploadSelfie(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    router.post('/verification/selfie', { photo: file }, { forceFormData: true, preserveScroll: true });
}

const newCode = () => router.post('/verification/code', {}, { preserveScroll: true });
</script>

<template>
    <StepShell
        heading="Four photos, that's all."
        lead="Enough to be real, not so many it's a chore. JPEG, PNG or WebP, up to 8 MB each."
    >
        <p v-if="problem" class="rv-photos__problem" role="alert">{{ problem }}</p>

        <div class="rv-grid rv-grid--2">
            <PhotoUploadSlot
                v-for="slot in SLOTS"
                :key="slot.key"
                :index="slot.index"
                :title="slot.title"
                :help="slot.help"
                :preview="previews[slot.key]?.url ?? null"
                :busy="busy === slot.key"
                @upload="(file) => upload(slot.key, file)"
                @remove="remove(slot.key)"
            />
        </div>

        <span class="rv-rule" />

        <!-- Visually separate on purpose: this photograph is not part of the
             profile and is never seen by another member. -->
        <BaseCard accent>
            <div class="rv-verify">
                <div class="rv-stack rv-verify__copy" style="gap: 20px">
                    <div class="rv-verify__head">
                        <h2 class="rv-d3">Verification photo</h2>
                        <span class="rv-pill">Required</span>
                    </div>

                    <p class="rv-body" style="max-width: 46ch">
                        {{ $t('profile.a_photo_of_you') }}
                    </p>

                    <div class="rv-stack" style="gap: 12px">
                        <span class="rv-label">Your code</span>
                        <div class="rv-verify__coderow">
                            <span class="rv-verify__code">{{ verification.code }}</span>
                            <button type="button" class="rv-link-action" @click="newCode">New code</button>
                        </div>
                    </div>

                    <p class="rv-small">
                        <span class="rv-label">{{ $t('profile.verification_note') }}</span>
                    </p>
                </div>

                <div class="rv-verify__frame">
                    <img
                        v-if="verification.selfie_url"
                        :src="verification.selfie_url"
                        alt="Your verification photo"
                        width="800"
                        height="600"
                        class="rv-verify__img"
                    />
                    <span v-else class="rv-verify__empty" aria-hidden="true">
                        <RvIcon name="upload" :size="28" />
                    </span>

                    <input
                        ref="selfieInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="rv-visually-hidden"
                        aria-label="Upload your verification photo"
                        @change="uploadSelfie"
                    />

                    <BaseButton variant="on-dark" class="rv-verify__action" @click="selfieInput.click()">
                        {{ verification.selfie_url ? 'Replace photo' : 'Upload verification photo' }}
                    </BaseButton>
                </div>
            </div>
        </BaseCard>
    </StepShell>
</template>

<style scoped>
.rv-photos__problem {
    margin: 0;
    padding: 14px 18px;
    border: 1px solid var(--rose);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--rose) 6%, var(--card));
    font-size: var(--fs-body-2);
    color: var(--ink);
    animation: rv-error-in 300ms var(--ease) both;
}

@keyframes rv-error-in {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

.rv-verify {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
    gap: 32px;
    align-items: start;
}

.rv-verify__copy {
    min-width: 0;
}

.rv-verify__head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
}

.rv-verify__coderow {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
}

.rv-verify__code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--control);
    padding: 0 24px;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--brass) 10%, var(--card));
    font-family: var(--display);
    font-size: var(--fs-display-3);
    line-height: 1;
    letter-spacing: 0.12em;
    color: var(--oxblood);
}

.rv-verify__frame {
    position: relative;
    overflow: hidden;
    min-width: 0;
    aspect-ratio: 4 / 3;
    border-radius: var(--r);
    border: 1px solid var(--hairline);
    background: linear-gradient(160deg, var(--oxblood) 0%, var(--oxblood-deep) 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
    padding: 16px;
}

.rv-verify__img {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.55;
}

.rv-verify__empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(185, 145, 84, 0.42);
}

.rv-verify__action {
    position: relative;
    min-height: var(--tap-min);
    padding: 0 16px;
}
</style>
