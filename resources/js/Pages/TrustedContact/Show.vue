<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseField from '@/Components/BaseField.vue';
import BaseRadio from '@/Components/BaseRadio.vue';

const props = defineProps({
    required: { type: Boolean, default: false },
    state: { type: Object, required: true },
    relationships: { type: Array, required: true },
    current: { type: Object, default: null },
    offerSupport: { type: Boolean, default: false },
});

const page = usePage();

const form = useForm({
    name: '',
    relationship: 'father',
    email: '',
    phone: '',
    date_of_birth: '',
});

const appointed = useForm({});

/**
 * ⚠️ THE FORM IS HIDDEN ONCE SOMEBODY IS NAMED, NOT DISABLED.
 *
 * A member who has already named somebody and is waiting should not be looking
 * at an empty form — it reads as though the first attempt did not save.
 */
const hasContact = computed(() => props.current !== null);

function submit() {
    form.post('/trusted-contact', { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Your trusted contact">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Your account</span>
                <h1 class="rv-d2">{{ $t('trusted_contact.heading') }}</h1>
                <p class="rv-lead" style="max-width: 56ch">{{ $t('trusted_contact.lead') }}</p>
            </header>

            <p
                v-if="page.props.flash?.success"
                class="rv-flash"
                role="status"
            >
                {{ page.props.flash.success }}
            </p>

            <!-- ⚠️ The member is always told where they stand. A flow that
                 stops with no visible reason reads as the product being
                 broken, and nobody can chase a person they were never told
                 they were waiting on. -->
            <!-- ⚠️ B9. She cannot see the decisions at all, so she has no way
                 of knowing this route exists. Telling her is the whole point;
                 waiting for her to ask is waiting for somebody to complain
                 about a process nobody showed her. -->
            <BaseCard v-if="offerSupport" accent>
                <div class="rv-stack" style="gap: 16px">
                    <p class="rv-body">{{ $t('support.unfair_prompt') }}</p>
                    <div class="rv-actions">
                        <BaseButton href="/support" variant="secondary">
                            {{ $t('support.heading') }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>

            <BaseCard v-if="state.message">
                <div class="rv-stack" style="gap: 8px">
                    <span class="rv-label rv-label--brass">{{ $t('trusted_contact.where_this_stands') }}</span>
                    <p class="rv-body">{{ state.message }}</p>
                </div>
            </BaseCard>

            <BaseCard v-if="hasContact">
                <div class="rv-stack" style="gap: 16px">
                    <h2 class="rv-d3">
                        {{ current.isAppointed ? $t('trusted_contact.arranging_heading') : current.name }}
                    </h2>
                    <p class="rv-body">
                        {{ current.isAppointed
                            ? $t('trusted_contact.arranging_body')
                            : $t('trusted_contact.invited_body') }}
                    </p>
                </div>
            </BaseCard>

            <BaseCard v-else>
                <form class="rv-stack" style="gap: 24px" @submit.prevent="submit">
                    <h2 class="rv-d3">{{ $t('trusted_contact.name_somebody') }}</h2>

                    <BaseField
                        v-model="form.name"
                        :label="$t('trusted_contact.their_name')"
                        :error="form.errors.name"
                        required
                    />

                    <div class="rv-stack" style="gap: 12px">
                        <span class="rv-label">{{ $t('trusted_contact.how_you_know_them') }}</span>
                        <div class="rv-stack" style="gap: 4px">
                            <BaseRadio
                                v-for="option in relationships"
                                :key="option.value"
                                v-model="form.relationship"
                                name="relationship"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </BaseRadio>
                        </div>
                        <span v-if="form.errors.relationship" class="rv-help" style="color: var(--rose)">
                            {{ form.errors.relationship }}
                        </span>
                    </div>

                    <BaseField
                        v-model="form.email"
                        :label="$t('trusted_contact.their_email')"
                        type="email"
                        :help="$t('trusted_contact.their_email_help')"
                        :error="form.errors.email"
                        required
                    />

                    <BaseField
                        v-model="form.phone"
                        :label="$t('trusted_contact.their_phone')"
                        type="tel"
                        :help="$t('trusted_contact.optional')"
                        :error="form.errors.phone"
                    />

                    <BaseField
                        v-model="form.date_of_birth"
                        :label="$t('trusted_contact.their_dob')"
                        type="date"
                        :help="$t('trusted_contact.their_dob_help')"
                        :error="form.errors.date_of_birth"
                    />

                    <div class="rv-actions">
                        <BaseButton type="submit" variant="primary" :disabled="form.processing">
                            {{ form.processing ? $t('trusted_contact.sending') : $t('trusted_contact.send_invitation') }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <!-- ⚠️ NO LIST TO CHOOSE FROM. There is deliberately no directory
                 of imams or organisations: nothing to type means nothing to
                 abuse, and a list would be a directory of real people
                 published by a platform whose premise is that it publishes
                 none. -->
            <BaseCard v-if="!hasContact">
                <div class="rv-stack" style="gap: 16px">
                    <h2 class="rv-d3">{{ $t('trusted_contact.none_heading') }}</h2>
                    <p class="rv-body">{{ $t('trusted_contact.none_body') }}</p>
                    <div class="rv-actions">
                        <BaseButton
                            variant="secondary"
                            :disabled="appointed.processing"
                            @click="appointed.post('/trusted-contact/appointed', { preserveScroll: true })"
                        >
                            {{ $t('trusted_contact.none_action') }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>
