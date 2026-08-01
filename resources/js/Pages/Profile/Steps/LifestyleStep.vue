<script setup>
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';
import TagInput from '@/Components/TagInput.vue';
import StepShell from './StepShell.vue';

const model = defineModel({ type: Object, required: true });

const props = defineProps({
    options: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

function togglePet(value, on) {
    const current = model.value.pets ?? [];
    model.value.pets = on ? [...current, value] : current.filter((pet) => pet !== value);
}
</script>

<template>
    <StepShell heading="How you live." lead="The everyday things that turn out to matter most once two people are actually together.">
        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <div class="rv-pair">
                    <BaseField v-model="model.education" label="Education" placeholder="Bachelor's degree" />
                    <BaseField v-model="model.occupation" label="Occupation" placeholder="Teacher" />
                </div>

                <div class="rv-pair">
                    <BaseField v-model="model.industry" label="Industry" placeholder="Education" />
                    <BaseField
                        v-model="model.income_range"
                        label="Income range"
                        placeholder="Optional"
                        help="Never shown to anyone. Optional."
                    />
                </div>

                <span class="rv-rule rv-rule--soft" />

                <BaseField v-model="model.religion" label="Religion" placeholder="Islam" />

                <SegmentedControl
                    v-model="model.faith_importance"
                    label="How important is a shared faith?"
                    :options="options.faithImportance"
                />

                <span class="rv-rule rv-rule--soft" />

                <SegmentedControl v-model="model.smoking" label="Do you smoke?" :options="options.frequency" />
                <SegmentedControl v-model="model.drinking" label="Do you drink?" :options="options.frequency" />

                <span class="rv-rule rv-rule--soft" />

                <SegmentedControl
                    v-model="model.has_children"
                    label="Do you have children?"
                    :options="options.hasChildren"
                />
                <SegmentedControl
                    v-model="model.wants_children"
                    label="Do you want children?"
                    :options="options.wantsChildren"
                />

                <span class="rv-rule rv-rule--soft" />

                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">Pets</span>
                    <div class="rv-stack" style="gap: 4px">
                        <BaseCheckbox
                            v-for="option in options.pets"
                            :key="option.value"
                            :model-value="(model.pets ?? []).includes(option.value)"
                            @update:model-value="togglePet(option.value, $event)"
                        >
                            {{ option.label }}
                        </BaseCheckbox>
                    </div>
                </div>

                <TagInput
                    v-model="model.languages"
                    label="Languages you speak"
                    placeholder="English, then press enter"
                    :max="10"
                    help="Press enter after each one."
                />
            </div>
        </BaseCard>
    </StepShell>
</template>
