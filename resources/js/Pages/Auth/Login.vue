<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import PasswordField from '@/Components/PasswordField.vue';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({ email: '', password: '', remember: false });

const submit = () => form.post('/login', { onFinish: () => form.reset('password') });
</script>

<template>
    <AuthLayout title="Log in">
        <BaseCard class="rv-auth__card">
            <form class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                <h1 class="rv-d3">Welcome back</h1>

                <p v-if="status" class="rv-auth__status" role="status">{{ status }}</p>

                <div class="rv-stack" style="gap: 24px">
                    <BaseField
                        v-model="form.email"
                        label="Email address"
                        type="email"
                        placeholder="you@example.com"
                        autocomplete="email"
                        :error="form.errors.email"
                        required
                    />

                    <PasswordField
                        v-model="form.password"
                        placeholder="Your password"
                        autocomplete="current-password"
                        :error="form.errors.password"
                    >
                        <template #aside>
                            <Link href="/forgot-password" class="rv-auth__aside">Forgot password?</Link>
                        </template>
                    </PasswordField>
                </div>

                <BaseButton type="submit" variant="primary" block :disabled="form.processing">
                    {{ form.processing ? 'Logging in' : 'Log in' }}
                </BaseButton>

                <span class="rv-rule"></span>

                <div class="rv-stack" style="gap: 20px">
                    <BaseButton href="/register" variant="secondary" block>Create a free account</BaseButton>
                    <p class="rv-small" style="text-align: center">Secure login · 18+</p>
                </div>
            </form>
        </BaseCard>
    </AuthLayout>
</template>
