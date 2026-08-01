<script setup>
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';
import StepShell from './StepShell.vue';

/**
 * ⚠️ The approved design showed a card here that revealed something derived
 * from the date of birth. It has been deleted, deliberately and completely —
 * the mechanism it displayed is internal and no member may learn of it.
 * The date of birth is now what it says it is: an age check.
 *
 * The 18+ rule is enforced on the server, not by this form.
 */
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
    <StepShell
        heading="Tell us about you."
        lead="Seven steps, saved as you go. Nothing here is public until you're verified."
    >
        <BaseCard>
            <div class="rv-stack" style="gap: 24px">
                <BaseField
                    v-model="model.first_name"
                    label="Preferred first name"
                    placeholder="Ayesha"
                    help="How matches will see you first."
                    :error="errors.first_name?.[0]"
                />

                <BaseField
                    v-model="model.last_name"
                    label="Last name"
                    placeholder="Siddiqui"
                    help="Kept private until you both express interest"
                    :error="errors.last_name?.[0]"
                />

                <BaseField
                    v-model="model.date_of_birth"
                    label="Date of birth"
                    type="date"
                    help="Used to confirm you're 18 or over"
                    :error="errors.date_of_birth?.[0]"
                />

                <span class="rv-rule rv-rule--soft" />

                <SegmentedControl v-model="model.gender" label="Gender" :options="options.gender" />
                <SegmentedControl v-model="model.seeking" label="I am seeking" :options="options.gender" />

                <span class="rv-rule rv-rule--soft" />

                <div class="rv-pair">
                    <BaseField v-model="model.city" label="City" placeholder="Kingston" :error="errors.city?.[0]" />
                    <BaseField v-model="model.country" label="Country" placeholder="Jamaica" :error="errors.country?.[0]" />
                </div>

                <BaseField
                    v-model="model.nationality"
                    label="Nationality"
                    placeholder="Optional"
                    :error="errors.nationality?.[0]"
                />

                <SegmentedControl
                    v-model="model.willing_to_relocate"
                    label="Willing to relocate?"
                    :options="YES_NO"
                />
            </div>
        </BaseCard>
    </StepShell>
</template>
