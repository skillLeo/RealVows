<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseField from '@/Components/BaseField.vue';
import PasswordField from '@/Components/PasswordField.vue';

const props = defineProps({
    token: { type: String, required: true },
    memberName: { type: String, default: null },
    relationship: { type: String, default: null },
    suggestedName: { type: String, default: null },
    suggestedEmail: { type: String, default: null },
});

/**
 * ⚠️ THE NAME AND EMAIL ARE PRE-FILLED FROM WHAT THE MEMBER TYPED, AND BOTH
 * ARE EDITABLE.
 *
 * She typed them from memory. Pre-filling saves a step for the common case;
 * locking them would mean a misspelled surname becomes permanent, and the
 * person it belongs to cannot correct it.
 */
const form = useForm({
    name: props.suggestedName ?? '',
    email: props.suggestedEmail ?? '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(`/trusted-contact/accept/${props.token}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout title="Accept an invitation">
        <BaseCard class="rv-auth__card">
            <form class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                <div class="rv-stack" style="gap: 16px">
                    <h1 class="rv-d3">
                        {{ memberName
                            ? $t('trusted_contact.accept_heading_named', { name: memberName })
                            : $t('trusted_contact.accept_heading_generic') }}
                    </h1>

                    <p class="rv-body">{{ $t('trusted_contact.accept_what_we_do') }}</p>

                    <!-- ⚠️ WHAT IS BEING ASKED, BEFORE THE ACCOUNT IS MADE.
                         Somebody deciding whether to accept needs to know what
                         they are agreeing to before they commit, not after. -->
                    <p class="rv-body">{{ $t('trusted_contact.accept_what_is_asked') }}</p>
                </div>

                <span class="rv-rule rv-rule--soft" />

                <div class="rv-stack" style="gap: 24px">
                    <BaseField
                        v-model="form.name"
                        :label="$t('trusted_contact.accept_your_name')"
                        :error="form.errors.name"
                        required
                    />

                    <BaseField
                        v-model="form.email"
                        :label="$t('trusted_contact.accept_your_email')"
                        type="email"
                        :help="$t('trusted_contact.accept_your_email_help')"
                        :error="form.errors.email"
                        required
                    />

                    <PasswordField
                        v-model="form.password"
                        autocomplete="new-password"
                        help="Eight characters or more, including one number."
                        :error="form.errors.password"
                    />

                    <PasswordField
                        v-model="form.password_confirmation"
                        label="Confirm password"
                        placeholder="Type it once more"
                        autocomplete="new-password"
                    />
                </div>

                <BaseButton type="submit" variant="primary" block :disabled="form.processing">
                    {{ form.processing ? $t('trusted_contact.accept_submitting') : $t('trusted_contact.accept_submit') }}
                </BaseButton>
            </form>
        </BaseCard>
    </AuthLayout>
</template>
