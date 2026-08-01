<script setup>
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import BaseRadio from '@/Components/BaseRadio.vue';
import StepShell from './StepShell.vue';

const model = defineModel({ type: Object, required: true });

defineProps({
    options: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const YES_NO = [
    { value: true, label: 'Yes' },
    { value: false, label: 'No' },
];
</script>

<template>
    <StepShell heading="A little about how you look." lead="Only what helps someone picture you. Nothing here is a test.">
        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <BaseField
                    v-model="model.height_cm"
                    label="Height in centimetres"
                    type="number"
                    inputmode="numeric"
                    placeholder="170"
                    :error="errors.height_cm?.[0]"
                />

                <!-- Six options, so radios rather than a segmented control -->
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">Body type</span>
                    <div class="rv-stack" style="gap: 4px">
                        <BaseRadio
                            v-for="option in options.bodyType"
                            :key="option.value"
                            v-model="model.body_type"
                            name="body_type"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </BaseRadio>
                    </div>
                </div>

                <span class="rv-rule rv-rule--soft" />

                <div class="rv-pair">
                    <BaseField v-model="model.eye_colour" label="Eye colour" placeholder="Brown" />
                    <BaseField v-model="model.hair_colour" label="Hair colour" placeholder="Black" />
                </div>

                <SegmentedControl v-model="model.has_tattoos" label="Any tattoos?" :options="YES_NO" />
                <SegmentedControl v-model="model.has_piercings" label="Any piercings?" :options="YES_NO" />
            </div>
        </BaseCard>
    </StepShell>
</template>
