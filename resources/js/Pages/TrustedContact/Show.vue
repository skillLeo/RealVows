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

/*
 * ⚠️ THE FORM WAS UNREACHABLE FOR EVERY MEMBER WHO MOST NEEDED IT.
 *
 * It rendered on `!hasContact` alone. But the gate tells a member whose
 * contact was rejected, or who has heard nothing for a fortnight, "You can
 * name somebody else" — and with a contact on record the form was not on the
 * page. The product asked them to act and gave them nothing to act with.
 *
 * The server has supported this the whole time: GuardianService marks the
 * previous nomination `replaced` and creates a new one. Only the front end had
 * a dead end in it.
 *
 * An appointed contact is excluded on purpose. That path is staff arranging
 * somebody on the member's behalf, and a form here would let her cancel an
 * arrangement she did not make and cannot see the state of.
 */
const canNominate = computed(() => {
    if (! hasContact.value) return true;
    if (props.current.isAppointed) return false;

    return ['rejected', 'stale'].includes(props.state?.state) || props.state?.mayReplace === true;
});

const isReplacing = computed(() => hasContact.value && canNominate.value);

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

            <!--
                ⚠️ THE INTRODUCTION TO THE IDEA, NOT JUST THE FORM.

                A member now lands here straight after finishing their profile
                rather than finding it in a menu, so for most people this is the
                first time they have met the concept at all. A form with no
                explanation in front of it reads as one more thing being asked
                of them, and they leave.

                Only shown when they have not named anybody. Somebody who
                already has a trusted contact does not need the idea explained
                to them again every time they open the page.
            -->
            <BaseCard v-if="!current" accent>
                <div class="rv-stack" style="gap: 14px">
                    <p class="rv-body"><strong>{{ $t('trusted_contact.who_they_are') }}</strong></p>
                    <p class="rv-body">{{ $t('trusted_contact.why_them') }}</p>
                    <p class="rv-help">{{ $t('trusted_contact.what_they_see') }}</p>
                    <p class="rv-help">{{ $t('trusted_contact.if_nobody') }}</p>
                </div>
            </BaseCard>

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

            <!-- ⚠️ v-if, NOT v-else. As a v-else on "has a contact" this form
                 was unreachable for exactly the members the gate was telling to
                 use it: somebody whose contact was turned down, or who has
                 heard nothing in a fortnight, was shown "You can name somebody
                 else" with no way to name anybody. -->
            <BaseCard v-if="canNominate">
                <form class="rv-stack" style="gap: 24px" @submit.prevent="submit">
                    <h2 class="rv-d3">
                        {{ isReplacing ? $t('trusted_contact.name_someone_else') : $t('trusted_contact.name_somebody') }}
                    </h2>

                    <!-- The reason, when there is one. A rejection a member
                         cannot understand is a support ticket waiting to
                         happen, and they cannot fix what they cannot see. -->
                    <p v-if="current?.rejectionReason" class="rv-body">
                        {{ $t('trusted_contact.rejected_because', { reason: current.rejectionReason }) }}
                    </p>

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
