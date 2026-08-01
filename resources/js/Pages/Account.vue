<script setup>
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseField from '@/Components/BaseField.vue';
import PasswordField from '@/Components/PasswordField.vue';
import VerifiedBadge from '@/Components/VerifiedBadge.vue';

const props = defineProps({
    account: { type: Object, required: true },
    countryCodes: { type: Array, required: true },
});

const page = usePage();

const contact = useForm({
    phone_country_code: props.account.phoneCountryCode ?? props.countryCodes[0]?.value,
    phone_number: props.account.phoneNumber ?? '',
});

const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const saveContact = () => contact.post('/account/contact', { preserveScroll: true });

const savePassword = () =>
    password.post('/account/password', {
        preserveScroll: true,
        onSuccess: () => password.reset(),
    });

const logout = () => router.post('/logout');
</script>

<template>
    <AppLayout title="Your account">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Your account</span>
                <h1 class="rv-d2">Settings</h1>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <BaseCard>
                <div class="rv-stack" style="gap: 20px">
                    <div class="rv-account__row">
                        <div class="rv-stack" style="gap: 6px">
                            <span class="rv-label">Email address</span>
                            <span class="rv-strong">{{ account.email }}</span>
                        </div>
                        <VerifiedBadge v-if="account.emailVerified" label="Confirmed" />
                        <span v-else class="rv-pill">Not confirmed</span>
                    </div>
                    <p class="rv-small">
                        Your email is how you sign in, so it cannot be changed here. Write to support
                        and we will move it for you.
                    </p>
                </div>
            </BaseCard>

            <BaseCard>
                <form class="rv-stack" style="gap: 24px" @submit.prevent="saveContact">
                    <div class="rv-stack" style="gap: 8px">
                        <h2 class="rv-d3">Mobile number</h2>
                        <p class="rv-body">This is where your matches are sent by WhatsApp.</p>
                    </div>

                    <div class="rv-field" :class="{ 'rv-field--error': !!contact.errors.phone_number }">
                        <div class="rv-field__head">
                            <label for="rv-account-phone" class="rv-label">Mobile number</label>
                            <VerifiedBadge v-if="account.phoneVerified" label="Verified" />
                            <span v-else class="rv-pill">Not verified yet</span>
                        </div>

                        <div class="rv-input-joined">
                            <select v-model="contact.phone_country_code" class="rv-select" aria-label="Country code">
                                <option v-for="code in countryCodes" :key="code.value" :value="code.value">
                                    {{ code.label }}
                                </option>
                            </select>
                            <input
                                id="rv-account-phone"
                                v-model="contact.phone_number"
                                class="rv-input"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel-national"
                            />
                        </div>

                        <span class="rv-help">
                            {{ contact.errors.phone_number || contact.errors.phone_country_code || 'Changing this number means it will need verifying again.' }}
                        </span>
                    </div>

                    <div>
                        <BaseButton type="submit" variant="primary" :disabled="contact.processing">
                            {{ contact.processing ? 'Saving' : 'Save number' }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseCard>
                <form class="rv-stack" style="gap: 24px" @submit.prevent="savePassword">
                    <h2 class="rv-d3">Change your password</h2>

                    <PasswordField
                        v-model="password.current_password"
                        label="Current password"
                        placeholder="Your current password"
                        autocomplete="current-password"
                        :error="password.errors.current_password"
                    />

                    <PasswordField
                        v-model="password.password"
                        label="New password"
                        autocomplete="new-password"
                        help="Eight characters or more, including one number."
                        :error="password.errors.password"
                    />

                    <PasswordField
                        v-model="password.password_confirmation"
                        label="Confirm new password"
                        placeholder="Type it once more"
                        autocomplete="new-password"
                    />

                    <div>
                        <BaseButton type="submit" variant="primary" :disabled="password.processing">
                            {{ password.processing ? 'Saving' : 'Change password' }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>

            <BaseCard v-if="account.isAdmin" accent>
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-label rv-label--brass">Administration</span>
                    <div class="rv-actions">
                        <BaseButton href="/admin/verifications" variant="secondary">Verification queue</BaseButton>
                        <BaseButton href="/admin/settings" variant="secondary">Settings</BaseButton>
                    </div>
                </div>
            </BaseCard>

            <BaseCard quiet>
                <div class="rv-account__row">
                    <div class="rv-stack" style="gap: 6px">
                        <span class="rv-strong">Signed in on this device</span>
                        <span class="rv-small">Your profile stays exactly as it is.</span>
                    </div>
                    <BaseButton variant="ghost" @click="logout">Log out</BaseButton>
                </div>
            </BaseCard>

            <p class="rv-small">
                <Link href="/terms">Terms of Service</Link> ·
                <Link href="/privacy">Privacy Policy</Link> ·
                <Link href="/community-guidelines">Community Guidelines</Link>
            </p>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-account__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 20px;
}
</style>
