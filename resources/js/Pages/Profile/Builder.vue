<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StepSidebar from '@/Components/StepSidebar.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { useAutosave } from '@/composables/useAutosave';

import AccountStep from './Steps/AccountStep.vue';
import PersonalStep from './Steps/PersonalStep.vue';
import PhysicalStep from './Steps/PhysicalStep.vue';
import PhotosStep from './Steps/PhotosStep.vue';
import LifestyleStep from './Steps/LifestyleStep.vue';
import ValuesStep from './Steps/ValuesStep.vue';
import PreferencesStep from './Steps/PreferencesStep.vue';

const props = defineProps({
    steps: { type: Array, required: true },
    stepKeys: { type: Array, required: true },
    currentStep: { type: String, required: true },
    currentStepNumber: { type: Number, required: true },
    options: { type: Object, required: true },
    profile: { type: Object, required: true },
    completeness: { type: Number, required: true },
    missing: { type: Array, required: true },
    submittable: { type: Boolean, required: true },
    submittedAt: { type: String, default: null },
    photos: { type: Object, default: () => ({}) },
    verification: { type: Object, default: () => ({}) },
});

const page = usePage();

const STEP_COMPONENTS = {
    account: AccountStep,
    personal: PersonalStep,
    physical: PhysicalStep,
    photos: PhotosStep,
    lifestyle: LifestyleStep,
    values: ValuesStep,
    preferences: PreferencesStep,
};

/** Account and Photos hold nothing this endpoint saves. */
const AUTOSAVING_STEPS = ['personal', 'physical', 'lifestyle', 'values', 'preferences'];

const model = ref({ ...props.profile[props.currentStep] });
const liveCompleteness = ref(props.completeness);
const liveMissing = ref(props.missing);
const liveSubmittable = ref(props.submittable);
const direction = ref('forward');

const autosaves = computed(() => AUTOSAVING_STEPS.includes(props.currentStep));

const { status, errors, flush } = useAutosave(
    model,
    `/profile/${props.currentStep}`,
    (body) => {
        liveCompleteness.value = body.completeness;
        liveMissing.value = body.missing;
        liveSubmittable.value = body.submittable;
    }
);

watch(
    () => props.currentStep,
    (step) => {
        model.value = { ...props.profile[step] };
        liveCompleteness.value = props.completeness;
        liveMissing.value = props.missing;
        liveSubmittable.value = props.submittable;
    }
);

const index = computed(() => props.stepKeys.indexOf(props.currentStep));
const isLast = computed(() => index.value === props.stepKeys.length - 1);
const progress = computed(() => `${(props.currentStepNumber / props.steps.length) * 100}%`);

const saveMessage = computed(
    () =>
        ({
            idle: 'Saved automatically as you type.',
            saving: 'Saving…',
            saved: 'Saved automatically as you type.',
            error: 'We could not save just then. Your answers are still here — we will try again.',
        })[status.value]
);

/** Photo uploads move the completeness figure too, so the header keeps up. */
function onPhotosUpdated(payload) {
    liveCompleteness.value = payload.completeness;
    liveMissing.value = payload.missing;
    liveSubmittable.value = payload.submittable;
}

async function go(stepKey, movement) {
    if (autosaves.value) await flush();
    direction.value = movement;
    router.visit(`/profile/${stepKey}`, { preserveScroll: false });
}

const next = () => (isLast.value ? submit() : go(props.stepKeys[index.value + 1], 'forward'));
const previous = () => index.value > 0 && go(props.stepKeys[index.value - 1], 'back');
const jump = (i) => i !== index.value && go(props.stepKeys[i], i > index.value ? 'forward' : 'back');

async function submit() {
    if (autosaves.value) await flush();
    router.post('/profile/submit', {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${steps[currentStepNumber - 1]} · Your profile`" />

    <AppLayout bare>
        <!-- Persistent header: step count in the display face, brass progress bar -->
        <template #bar>
            <div class="rv-shell rv-builder__meta">
                <span class="rv-builder__count">Step {{ currentStepNumber }} of {{ steps.length }}</span>
                <span class="rv-builder__pct">{{ liveCompleteness }}% complete</span>
            </div>
            <div class="rv-progress">
                <div class="rv-progress__fill" :style="{ width: progress }" />
            </div>
        </template>

        <div class="rv-shell rv-builder">
            <StepSidebar :steps="steps" :current="index" @select="jump" />

            <div class="rv-builder__main">
                <Transition :name="direction === 'forward' ? 'rv-slide' : 'rv-slide-back'" mode="out-in">
                    <component
                        :is="STEP_COMPONENTS[currentStep]"
                        :key="currentStep"
                        v-model="model"
                        :options="options"
                        :errors="errors"
                        :account="profile.account"
                        :photos="photos"
                        :verification="verification"
                        :missing="liveMissing"
                        :completeness="liveCompleteness"
                        @updated="onPhotosUpdated"
                    />
                </Transition>
            </div>
        </div>

        <template #actionbar>
            <div class="rv-actionbar rv-has-tabbar">
                <div class="rv-shell rv-builder__actions">
                    <div class="rv-builder__actions-row">
                        <BaseButton
                            v-if="index > 0"
                            variant="ghost"
                            @click="previous"
                        >
                            Back
                        </BaseButton>
                        <span v-else />

                        <BaseButton variant="primary" @click="next">
                            {{ isLast ? 'Submit for verification' : 'Continue' }}
                        </BaseButton>
                    </div>

                    <p
                        class="rv-small rv-builder__savenote"
                        :class="{ 'is-error': status === 'error' }"
                        role="status"
                        aria-live="polite"
                    >
                        {{ autosaves ? saveMessage : 'Your answers are kept as you go.' }}
                    </p>

                    <p v-if="page.props.flash?.error" class="rv-builder__missing" role="alert">
                        {{ page.props.flash.error }}
                    </p>
                </div>
            </div>
        </template>
    </AppLayout>
</template>

<style scoped>
.rv-builder__meta {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding-bottom: 16px;
}

.rv-builder__count {
    font-family: var(--display);
    font-size: var(--fs-body-1);
    line-height: 1;
    color: var(--brass-bright);
}

.rv-builder__pct {
    font-size: var(--fs-body-3);
    font-weight: 500;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--brass);
}

.rv-builder {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 32px 48px;
    /* 48px between sections in the app; 96px is for marketing pages */
    padding-block: var(--gap-app) 32px;
}

.rv-builder__main {
    flex: 1 1 420px;
    min-width: 0;
}

.rv-builder__actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-block: 20px;
}

.rv-builder__actions-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}

.rv-builder__savenote {
    text-align: center;
}

.rv-builder__savenote.is-error {
    color: var(--rose);
}

.rv-builder__missing {
    margin: 0;
    padding: 14px 18px;
    border: 1px solid var(--rose);
    border-radius: var(--r);
    background: color-mix(in oklab, var(--rose) 6%, var(--card));
    font-size: var(--fs-body-3);
    line-height: 1.55;
    color: var(--ink);
}

/* Forward slides left, back slides right */
.rv-slide-back-enter-active,
.rv-slide-back-leave-active {
    transition:
        opacity 300ms var(--ease),
        transform 300ms var(--ease);
}
.rv-slide-back-enter-from {
    opacity: 0;
    transform: translateX(-24px);
}
.rv-slide-back-leave-to {
    opacity: 0;
    transform: translateX(24px);
}
</style>
