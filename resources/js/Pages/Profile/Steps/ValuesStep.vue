<script setup>
import { computed, ref, useId } from 'vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import BaseRadio from '@/Components/BaseRadio.vue';
import TagInput from '@/Components/TagInput.vue';
import ValueRanker from '@/Components/ValueRanker.vue';
import PersonalityScale from '@/Components/PersonalityScale.vue';
import StepShell from './StepShell.vue';
import BaseButton from '@/Components/BaseButton.vue';
import { usePage } from '@inertiajs/vue3';

const model = defineModel({ type: Object, required: true });

const props = defineProps({
    options: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const bioId = useId();
const page = usePage();

/*
 * ⚠️ THE BUTTON IS ABSENT WHEN NO KEY IS CONFIGURED, NOT DISABLED.
 *
 * A greyed-out "tidy my English" advertises a feature the member cannot have
 * and invites them to ask why. With no key it simply does not exist.
 */
const writerEnabled = computed(() => page.props.writerEnabled === true);

const suggestion = ref(null);
const writing = ref(false);
const writerNote = ref(null);

async function suggest() {
    writing.value = true;
    suggestion.value = null;
    writerNote.value = null;

    try {
        const response = await fetch('/profile/rewrite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ bio: model.value.bio ?? '' }),
        });

        const data = await response.json();

        /*
         * ⚠️ A NULL REWRITE IS NOT AN ERROR. The service discards anything that
         * invented a fact, and the member is told their own words are fine
         * rather than that a model misbehaved.
         */
        if (!data.rewrite) {
            writerNote.value = data.message ?? null;
            return;
        }

        suggestion.value = data.rewrite;
    } catch {
        writerNote.value = null;
    } finally {
        writing.value = false;
    }
}

/*
 * ⚠️ ACCEPTING ONLY CHANGES THE FIELD. It is saved by the ordinary profile
 * save, like anything else the member typed, so nothing is written because a
 * button was pressed.
 */
function accept() {
    /*
     * ⚠️ THEIR OWN WORDS ARE CARRIED ALONGSIDE, not discarded. The server
     * writes it once and never overwrites, so editing the rewrite afterwards
     * cannot replace what they originally typed.
     */
    model.value.bio_original = model.value.bio;
    model.value.bio = suggestion.value;
    suggestion.value = null;
}

const bioWords = computed(() => {
    const text = (model.value.bio ?? '').trim();
    return text ? text.split(/\s+/).length : 0;
});

const bioState = computed(() => {
    if (bioWords.value === 0) return 'empty';
    if (bioWords.value < props.options.bioMinWords) return 'short';
    if (bioWords.value > props.options.bioMaxWords) return 'long';
    return 'good';
});

const bioMessage = computed(
    () =>
        ({
            empty: `${props.options.bioMinWords} to ${props.options.bioMaxWords} words. Take your time with this one.`,
            short: `${bioWords.value} words. A little more, up to ${props.options.bioMinWords}.`,
            long: `${bioWords.value} words, slightly over ${props.options.bioMaxWords}. Trim it a touch.`,
            good: `${bioWords.value} words. That reads well.`,
        })[bioState.value]
);

function toggleLoveLanguage(value, on) {
    const current = model.value.love_languages ?? [];
    if (on && current.length >= 3) return;
    model.value.love_languages = on ? [...current, value] : current.filter((entry) => entry !== value);
}

function scaleValue(key) {
    return model.value.personality_scales?.[key] ?? 50;
}

function setScale(key, value) {
    model.value.personality_scales = { ...(model.value.personality_scales ?? {}), [key]: value };
}
</script>

<template>
    <StepShell
        heading="What you value."
        lead="The part that does the most work. Say what is true rather than what sounds good. It is what you will be matched on."
    >
        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <!-- Three options: radios rather than a segmented control,
                     because each label is a full sentence -->
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">{{ $t('profile.looking_for') }}</span>
                    <div class="rv-stack" style="gap: 4px">
                        <BaseRadio
                            v-for="option in options.relationshipGoal"
                            :key="option.value"
                            v-model="model.relationship_goal"
                            name="relationship_goal"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </BaseRadio>
                    </div>
                </div>

                <SegmentedControl
                    v-model="model.marriage_timeline"
                    label="Your timeframe for a lasting relationship"
                    :options="options.marriageTimeline"
                />
            </div>
        </BaseCard>

        <BaseCard>
            <ValueRanker v-model="model.core_values" :options="options.coreValues" :max="5" />
        </BaseCard>

        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">{{ $t('profile.on_disagreement') }}</span>
                    <div class="rv-stack" style="gap: 4px">
                        <BaseRadio
                            v-for="option in options.conflictStyle"
                            :key="option.value"
                            v-model="model.conflict_style"
                            name="conflict_style"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </BaseRadio>
                    </div>
                </div>

                <span class="rv-rule rv-rule--soft" />

                <div class="rv-stack" style="gap: 12px">
                    <div class="rv-field__head">
                        <span class="rv-label">{{ $t('profile.on_affection') }}</span>
                        <span class="rv-help">{{ (model.love_languages ?? []).length }} of 3</span>
                    </div>
                    <div class="rv-stack" style="gap: 4px">
                        <BaseCheckbox
                            v-for="option in options.loveLanguages"
                            :key="option.value"
                            :model-value="(model.love_languages ?? []).includes(option.value)"
                            :disabled="
                                (model.love_languages ?? []).length >= 3 &&
                                !(model.love_languages ?? []).includes(option.value)
                            "
                            @update:model-value="toggleLoveLanguage(option.value, $event)"
                        >
                            {{ option.label }}
                        </BaseCheckbox>
                    </div>
                </div>
            </div>
        </BaseCard>

        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <div class="rv-field" :class="{ 'rv-field--error': bioState === 'long' }">
                    <div class="rv-field__head">
                        <label :for="bioId" class="rv-label">{{ $t('profile.about_you') }}</label>
                        <span class="rv-help">{{ bioMessage }}</span>
                    </div>

                    <textarea
                        :id="bioId"
                        v-model="model.bio"
                        class="rv-input rv-textarea"
                        rows="10"
                        :placeholder="options.bioPrompts.join('\n\n')"
                    />

                    <span class="rv-help">
                        {{ $t('profile.prompts_are_hints') }}
                    </span>

                    <!-- ⚠️ Absent, not disabled, when no key is configured. -->
                    <div v-if="writerEnabled && bioWords >= 12" class="rv-stack" style="gap: 12px">
                        <div class="rv-actions">
                            <BaseButton
                                variant="secondary"
                                :disabled="writing"
                                @click="suggest"
                            >
                                {{ writing ? $t('writer.working') : $t('writer.offer') }}
                            </BaseButton>
                        </div>
                        <span class="rv-help">{{ $t('writer.promise') }}</span>
                    </div>

                    <p v-if="writerNote" class="rv-help">{{ writerNote }}</p>

                    <!-- ⚠️ BOTH VERSIONS, SIDE BY SIDE. The member chooses; the
                         rewrite never replaces anything on its own. -->
                    <div v-if="suggestion" class="rv-writer">
                        <div class="rv-writer__col">
                            <span class="rv-label">{{ $t('writer.yours') }}</span>
                            <p class="rv-body">{{ model.bio }}</p>
                        </div>
                        <div class="rv-writer__col rv-writer__col--suggested">
                            <span class="rv-label rv-label--brass">{{ $t('writer.ours') }}</span>
                            <p class="rv-body">{{ suggestion }}</p>
                        </div>

                        <div class="rv-actions rv-writer__actions">
                            <BaseButton variant="primary" @click="accept">
                                {{ $t('writer.use_theirs') }}
                            </BaseButton>
                            <BaseButton variant="secondary" @click="suggestion = null">
                                {{ $t('writer.keep_mine') }}
                            </BaseButton>
                        </div>
                    </div>
                </div>

                <TagInput
                    v-model="model.interests"
                    label="Interests"
                    placeholder="Cooking, then press enter"
                    :max="12"
                />
            </div>
        </BaseCard>

        <BaseCard>
            <div class="rv-stack" style="gap: 32px">
                <div class="rv-stack" style="gap: 8px">
                    <h2 class="rv-d3">Where you sit</h2>
                    <p class="rv-body">{{ $t('profile.no_better_end') }}</p>
                </div>

                <PersonalityScale
                    v-for="(scale, key) in options.personalityScales"
                    :key="key"
                    :model-value="scaleValue(key)"
                    :left="scale.left"
                    :right="scale.right"
                    @update:model-value="setScale(key, $event)"
                />
            </div>
        </BaseCard>
    </StepShell>
</template>
