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
    missingByStep: { type: Object, default: () => ({}) },
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
const liveMissingByStep = ref(props.missingByStep);

/*
 * ⚠️ WHAT IS STILL SHORT ON THE STEP THE MEMBER IS ACTUALLY LOOKING AT.
 *
 * Every shortfall used to surface at the very end of a seven-step form. Somebody
 * who left one field blank on step three reached the last screen, was refused,
 * and had to work out for themselves which step the missing thing lived on.
 *
 * Telling them here means the refusal is next to the field, while they still
 * remember what they were doing.
 */
const blocked = ref(false);

const shortOnThisStep = computed(() => liveMissingByStep.value?.[props.step] ?? []);

/*
 * ⚠️ THE WARNING CLEARS ITSELF THE MOMENT THE STEP IS COMPLETE. A message that
 * stays after the member has fixed the thing reads as the form being broken,
 * and they stop trusting the next one.
 */
watch(shortOnThisStep, (still) => {
    if (still.length === 0) blocked.value = false;
});
const liveSubmittable = ref(props.submittable);
const direction = ref('forward');

const autosaves = computed(() => AUTOSAVING_STEPS.includes(props.currentStep));

const { status, errors, flush } = useAutosave(
    model,
    `/profile/${props.currentStep}`,
    (body) => {
        liveCompleteness.value = body.completeness;
        liveMissing.value = body.missing;
        liveMissingByStep.value = body.missingByStep ?? liveMissingByStep.value;
        liveSubmittable.value = body.submittable;
    }
);

watch(
    () => props.currentStep,
    (step) => {
        model.value = { ...props.profile[step] };
        liveCompleteness.value = props.completeness;
        liveMissing.value = props.missing;
        liveMissingByStep.value = props.missingByStep;
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
            error: 'We could not save just then. Your answers are still here and we will try again.',
        })[status.value]
);

/**
 * The same state, as a word rather than a sentence.
 *
 * ⚠️ IDLE SAYS NOTHING, AND THAT IS THE WHOLE SAVING.
 *
 * The long form said "Saved automatically as you type." permanently, including
 * before anything had been typed — a promise, a status and a piece of
 * furniture at once, occupying a row of the action bar on every step forever.
 *
 * Here it is blank until something happens. A word that appears when the state
 * changes is read; a sentence that is always there is furniture.
 *
 * ⚠️ THE ERROR KEEPS ITS FULL SENTENCE. "We could not save" as one word would
 * be alarming and useless — the member needs to know their answers are still
 * there and that we will try again, and that does not fit in a word.
 */
const shortSaveState = computed(
    () =>
        ({
            idle: '',
            saving: 'Saving…',
            saved: 'Saved',
            error: 'Not saved — we will try again',
        })[status.value]
);

/** Photo uploads move the completeness figure too, so the header keeps up. */
function onPhotosUpdated(payload) {
    liveCompleteness.value = payload.completeness;
    liveMissing.value = payload.missing;
    liveMissingByStep.value = payload.missingByStep ?? liveMissingByStep.value;
    liveSubmittable.value = payload.submittable;
}

async function go(stepKey, movement) {
    if (autosaves.value) await flush();
    direction.value = movement;
    router.visit(`/profile/${stepKey}`, { preserveScroll: false });
}

/*
 * ⚠️ THE STEP HAS TO BE FINISHED BEFORE THE NEXT ONE OPENS.
 *
 * Advancing freely and refusing at the end is the thing the client asked to be
 * fixed, and he is right: a member arriving at step seven and being told
 * something is missing has no idea which step it was on. Autosave flushes first,
 * so the answer typed a second ago counts.
 *
 * ⚠️ IT ANNOUNCES RATHER THAN JUST BLOCKING. A Continue button that quietly does
 * nothing is indistinguishable from a broken one, and a member with no
 * explanation presses it again and then leaves.
 */
const next = async () => {
    if (autosaves.value) await flush();

    if (shortOnThisStep.value.length > 0) {
        blocked.value = true;

        return;
    }

    blocked.value = false;

    return isLast.value ? submit() : go(props.stepKeys[index.value + 1], 'forward');
};
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
        <!-- ⚠️ THE PROGRESS IS PART OF THE PAGE, NOT PART OF THE CHROME.
             It was bolted onto the app's top bar, which made the header three
             rows deep on every step and pushed the actual question further
             down a screen that is mostly question. A step counter is about the
             form, not about the product, and it belongs with the form. -->
        <div class="rv-shell rv-builder">
            <StepSidebar :steps="steps" :current="index" @select="jump" />

            <div class="rv-builder__main">
                <div class="rv-builder__head">
                    <div class="rv-builder__meta">
                        <span class="rv-builder__count">Step {{ currentStepNumber }} of {{ steps.length }}</span>

                        <!-- ⚠️ THE SAVE STATE MOVED HERE FROM A FULL LINE AT
                             THE BOTTOM OF THE PAGE.
                             "Saved automatically as you type." sat permanently
                             under the buttons, costing a row of the action bar
                             to say the same thing on every step forever. The
                             reassurance is worth keeping and the sentence is
                             not: as a word beside the percentage it is read
                             when it changes and ignored when it does not. -->
                        <span
                            class="rv-builder__save"
                            :class="{ 'is-error': status === 'error' }"
                            role="status"
                            aria-live="polite"
                        >{{ shortSaveState }}</span>

                        <span class="rv-builder__pct">{{ liveCompleteness }}%</span>
                    </div>

                    <div class="rv-progress">
                        <div class="rv-progress__fill" :style="{ width: progress }" />
                    </div>
                </div>

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

                <!--
                    ⚠️ THE REFUSAL BELONGS ON THE STEP THAT CAUSED IT.

                    Everything short used to be listed on the final screen, after
                    seven steps, with no indication of which step it came from.
                    The client's words: the user is confused about what is left
                    or missed or wrong.

                    Shown here it is next to the field, while they still remember
                    what they were doing, and only after they press Continue — so
                    a half-filled step they are still working on is never scolded.
                -->
                <p
                    v-if="blocked && shortOnThisStep.length"
                    class="rv-builder__missing"
                    role="alert"
                    aria-live="polite"
                >
                    <strong>{{ $t('profile.before_you_continue') }}</strong>
                    {{ shortOnThisStep.join(', ') }}.
                </p>
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

                    <p v-if="page.props.flash?.error" class="rv-builder__missing" role="alert">
                        {{ page.props.flash.error }}
                    </p>
                </div>
            </div>
        </template>
    </AppLayout>
</template>

<style scoped>

/* ── The step header, in the page ────────────────────────────────────────
   One line and a 3px bar, where the top-bar version was two stacked rows of
   chrome above the app header. */
.rv-builder__head {
    display: grid;
    gap: 8px;
    margin-block-end: 20px;
}

.rv-builder__save {
    /* Reserves nothing when empty: an idle builder shows no word at all and
       the line does not jump when one appears. */
    font-size: 12px;
    color: var(--ink-soft);
    margin-inline-start: auto;
    font-variant-numeric: tabular-nums;
}

.rv-builder__save.is-error { color: var(--rose); }

.rv-builder__meta {
    display: flex;
    align-items: baseline;
    gap: 12px;
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

/* ⚠️ SIZED FOR WHAT IS ACTUALLY IN IT NOW.
 *
 * This held three rows — buttons, a permanent save sentence, and an error slot
 * — so it was padded for three and reserved the height whether or not the
 * other two had anything to say. On the profile builder, which is a form
 * somebody scrolls, that is a strip of the screen taken from the question
 * being asked.
 *
 * One row of buttons, and the error appears only when there is one. */
.rv-builder__actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-block: 12px;
}

.rv-builder__actions-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
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
