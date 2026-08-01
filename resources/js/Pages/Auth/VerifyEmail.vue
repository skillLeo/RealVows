<script setup>
import { router, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import RvIcon from '@/Components/RvIcon.vue';

defineProps({
    email: { type: String, required: true },
    status: { type: String, default: null },
});

const form = useForm({});

const resend = () => form.post('/email/verification-notification', { preserveScroll: true });
const logout = () => router.post('/logout');
</script>

<template>
    <AuthLayout title="Confirm your email">
        <BaseCard class="rv-auth__card">
            <div class="rv-stack" style="gap: 32px">
                <span class="rv-auth__tick">
                    <RvIcon name="envelope" :size="20" />
                </span>

                <div class="rv-stack" style="gap: 16px">
                    <h1 class="rv-d3">One quick confirmation</h1>
                    <p class="rv-body">
                        We've sent a link to <strong>{{ email }}</strong>. Open it and your account
                        is ready — then you can start building your profile.
                    </p>
                </div>

                <p v-if="status" class="rv-auth__status" role="status">{{ status }}</p>

                <span class="rv-rule"></span>

                <div class="rv-stack" style="gap: 20px">
                    <BaseButton variant="primary" block :disabled="form.processing" @click="resend">
                        {{ form.processing ? 'Sending' : 'Send the link again' }}
                    </BaseButton>

                    <p class="rv-small" style="text-align: center">
                        Wrong address?
                        <button type="button" class="rv-link-brass rv-link-inline" @click="logout">
                            Log out and start again
                        </button>
                    </p>
                </div>
            </div>
        </BaseCard>
    </AuthLayout>
</template>
