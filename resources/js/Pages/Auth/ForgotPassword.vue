<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import RvIcon from '@/Components/RvIcon.vue';

const page = usePage();
const form = useForm({ email: '' });

// The success state is the same whether or not the address is registered.
const sent = computed(() => !!page.props.flash?.reset_link_sent || !!form.recentlySuccessful);

const submit = () => form.post('/forgot-password', { preserveScroll: true });
</script>

<template>
    <AuthLayout title="Reset your password">
        <BaseCard class="rv-auth__card">
            <!-- Sent -->
            <div v-if="sent" class="rv-stack" style="gap: 32px">
                <span class="rv-auth__tick">
                    <RvIcon name="check" :size="20" :stroke-width="1.75" />
                </span>

                <div class="rv-stack" style="gap: 16px">
                    <h1 class="rv-d3">{{ $t('auth.forgot_sent_heading') }}</h1>
                    <p class="rv-body">
                        {{ $t('auth.forgot_sent_body') }}
                    </p>
                </div>

                <span class="rv-rule"></span>

                <div class="rv-stack" style="gap: 20px">
                    <BaseButton href="/login" variant="secondary" block>Back to log in</BaseButton>
                    <p class="rv-small" style="text-align: center">
                        {{ $t('auth.forgot_not_arrived') }}
                        <button type="button" class="rv-link-brass rv-link-inline" @click="form.post('/forgot-password', { preserveScroll: true })">
                            {{ $t('auth.send_again') }}
                        </button>
                    </p>
                </div>
            </div>

            <!-- Request -->
            <form v-else class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                <div class="rv-stack" style="gap: 16px">
                    <h1 class="rv-d3">{{ $t('auth.forgot_heading') }}</h1>
                    <p class="rv-body">
                        {{ $t('auth.forgot_body') }}
                    </p>
                </div>

                <BaseField
                    v-model="form.email"
                    label="Email address"
                    type="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    :error="form.errors.email"
                    required
                />

                <BaseButton type="submit" variant="primary" block :disabled="form.processing">
                    {{ form.processing ? 'Sending' : 'Send reset link' }}
                </BaseButton>

                <span class="rv-rule"></span>

                <p class="rv-body" style="text-align: center">
                    Remembered it? <Link href="/login">Log in</Link>
                </p>
            </form>
        </BaseCard>
    </AuthLayout>
</template>
