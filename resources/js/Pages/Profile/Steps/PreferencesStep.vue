<script setup>
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import TagInput from '@/Components/TagInput.vue';
import StepShell from './StepShell.vue';

const model = defineModel({ type: Object, required: true });

defineProps({
    options: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

function toggleIn(field, value, on) {
    const current = model.value[field] ?? [];
    model.value[field] = on ? [...current, value] : current.filter((entry) => entry !== value);
}
</script>

<template>
    <StepShell
        heading="Who you're hoping to meet."
        lead="This shapes who we bring you. Both sides are weighed, so a match is never one person's wishes imposed on another's."
    >
        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <div class="rv-pair">
                    <BaseField
                        v-model="model.age_min"
                        label="Youngest age"
                        type="number"
                        inputmode="numeric"
                        placeholder="25"
                        :error="errors.age_min?.[0]"
                    />
                    <BaseField
                        v-model="model.age_max"
                        label="Oldest age"
                        type="number"
                        inputmode="numeric"
                        placeholder="38"
                        :error="errors.age_max?.[0]"
                    />
                </div>

                <div class="rv-pair">
                    <BaseField
                        v-model="model.height_min_cm"
                        label="Shortest height (cm)"
                        type="number"
                        inputmode="numeric"
                        placeholder="155"
                        :error="errors.height_min_cm?.[0]"
                    />
                    <BaseField
                        v-model="model.height_max_cm"
                        label="Tallest height (cm)"
                        type="number"
                        inputmode="numeric"
                        placeholder="190"
                        :error="errors.height_max_cm?.[0]"
                    />
                </div>

                <SegmentedControl
                    v-model="model.distance_preference"
                    label="How far would you look?"
                    :options="options.distancePreference"
                />
            </div>
        </BaseCard>

        <BaseCard>
            <div class="rv-stack" style="gap: 12px">
                <span class="rv-label">Body types you are open to</span>
                <p class="rv-help">Leave them all unticked if you would rather not narrow this.</p>
                <div class="rv-stack" style="gap: 4px">
                    <BaseCheckbox
                        v-for="option in options.bodyType"
                        :key="option.value"
                        :model-value="(model.body_types ?? []).includes(option.value)"
                        @update:model-value="toggleIn('body_types', option.value, $event)"
                    >
                        {{ option.label }}
                    </BaseCheckbox>
                </div>
            </div>
        </BaseCard>

        <BaseCard accent>
            <div class="rv-stack" style="gap: 12px">
                <span class="rv-label rv-label--brass">Deal-breakers</span>
                <p class="rv-body">
                    These are absolute. Anyone they apply to is removed before any scoring happens, so choose
                    only what you genuinely could not live with.
                </p>
                <div class="rv-stack" style="gap: 4px">
                    <BaseCheckbox
                        v-for="option in options.dealBreakers"
                        :key="option.value"
                        :model-value="(model.deal_breakers ?? []).includes(option.value)"
                        @update:model-value="toggleIn('deal_breakers', option.value, $event)"
                    >
                        {{ option.label }}
                    </BaseCheckbox>
                </div>
            </div>
        </BaseCard>

        <BaseCard>
            <TagInput
                v-model="model.preferred_qualities"
                label="Qualities you hope for"
                placeholder="Patient, then press enter"
                :max="5"
                help="A few words, not a list of demands."
            />
        </BaseCard>
    </StepShell>
</template>
