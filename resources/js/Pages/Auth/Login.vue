<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import PasswordField from '@/Components/PasswordField.vue';

const props = defineProps({
    status: { type: String, default: null },
    demoAccounts: { type: Array, default: () => [] },
});

// Grouped only for display. The array arrives empty on anything that is not
// a local demo, so this whole panel disappears rather than being hidden.
const demoGroups = computed(() =>
    props.demoAccounts.reduce((groups, account) => {
        (groups[account.group] ??= []).push(account);
        return groups;
    }, {})
);

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

        <!-- Local demo only. Never rendered anywhere else. -->
        <BaseCard v-if="demoAccounts.length" accent class="rv-auth__card rv-demo">
            <div class="rv-stack" style="gap: 20px">
                <div class="rv-stack" style="gap: 8px">
                    <span class="rv-label rv-label--brass">Demo · local only</span>
                    <p class="rv-small">
                        One click signs you straight in. Password for every account is
                        <strong>realvows2026</strong>.
                    </p>
                </div>

                <div v-for="(accounts, group) in demoGroups" :key="group" class="rv-stack" style="gap: 8px">
                    <span class="rv-label">{{ group }}</span>
                    <div class="rv-demo__row">
                        <a
                            v-for="account in accounts"
                            :key="account.key"
                            :href="`/demo-login/${account.key}`"
                            class="rv-demo__btn"
                        >
                            {{ account.label }}
                        </a>
                    </div>
                </div>
            </div>
        </BaseCard>
    </AuthLayout>
</template>

<style scoped>
.rv-demo__row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.rv-demo__btn {
    min-height: var(--tap-min);
    display: inline-flex;
    align-items: center;
    padding: 0 14px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--cream);
    color: var(--oxblood);
    font-size: var(--fs-body-3);
    font-weight: 500;
    text-decoration: none;
    transition:
        background var(--dur-tap) var(--ease),
        border-color var(--dur-tap) var(--ease),
        transform var(--dur-tap) var(--ease);
}

.rv-demo__btn:hover {
    border-color: var(--brass-bright);
    background: color-mix(in oklab, var(--brass) 12%, var(--cream));
    color: var(--oxblood);
}

.rv-demo__btn:active {
    transform: scale(0.98);
}
</style>
