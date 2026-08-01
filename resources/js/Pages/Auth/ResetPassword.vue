<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import PasswordField from '@/Components/PasswordField.vue';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () =>
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <AuthLayout title="Choose a new password">
        <BaseCard class="rv-auth__card">
            <form class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                <div class="rv-stack" style="gap: 16px">
                    <h1 class="rv-d3">Choose a new password</h1>
                    <p class="rv-body">Pick something you haven't used here before.</p>
                </div>

                <div class="rv-stack" style="gap: 24px">
                    <BaseField
                        v-model="form.email"
                        label="Email address"
                        type="email"
                        autocomplete="email"
                        :error="form.errors.email"
                        required
                    />

                    <PasswordField
                        v-model="form.password"
                        label="New password"
                        autocomplete="new-password"
                        help="Eight characters or more, including one number."
                        :error="form.errors.password"
                    />

                    <PasswordField
                        v-model="form.password_confirmation"
                        label="Confirm new password"
                        placeholder="Type it once more"
                        autocomplete="new-password"
                    />
                </div>

                <BaseButton type="submit" variant="primary" block :disabled="form.processing">
                    {{ form.processing ? 'Saving' : 'Save new password' }}
                </BaseButton>
            </form>
        </BaseCard>
    </AuthLayout>
</template>
