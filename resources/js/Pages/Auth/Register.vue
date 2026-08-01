<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';
import PasswordField from '@/Components/PasswordField.vue';

const props = defineProps({
    countryCodes: { type: Array, required: true },
});

const form = useForm({
    email: '',
    password: '',
    password_confirmation: '',
    phone_country_code: props.countryCodes[0]?.value ?? '+1',
    phone_number: '',
    is_adult: false,
    accepts_terms: false,
});

const submit = () =>
    form.post('/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Create your free account" />

    <div class="rv-split">
        <!-- Left: the promise, full-bleed oxblood -->
        <section class="rv-split__panel rv-on-dark">
            <Link href="/" class="rv-logo" aria-label="RealVows home">
                <span class="rv-logo__mark" aria-hidden="true">RV</span>
                <span class="rv-logo__word">RealVows</span>
            </Link>

            <div class="rv-split__copy rv-stack" style="gap: 32px">
                <h1 class="rv-d1" style="max-width: 20ch">Join people who are serious about marriage.</h1>
                <p class="rv-lead" style="max-width: 46ch">
                    Sign up is completely free. Build your entire profile without paying anything.
                </p>
                <p class="rv-hero__eyebrow">
                    <span>Marriage-first</span>
                    <span>18+</span>
                    <span>Verified members</span>
                </p>
            </div>
        </section>

        <!-- Right: the form, on cream -->
        <section class="rv-split__form">
            <BaseCard class="rv-auth__card">
                <form class="rv-stack" style="gap: 32px" @submit.prevent="submit">
                    <h2 class="rv-d3">Create your account</h2>

                    <div class="rv-stack" style="gap: 24px">
                        <BaseField
                            v-model="form.email"
                            label="Email address"
                            type="email"
                            placeholder="you@example.com"
                            autocomplete="email"
                            help="Your matches are sent here too."
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

                        <div class="rv-field" :class="{ 'rv-field--error': !!form.errors.phone_number }">
                            <div class="rv-field__head">
                                <label for="rv-mobile" class="rv-label">Mobile number</label>
                                <!-- The slot for phone verification exists from day one.
                                     Nothing sends yet; it is wired to WhatsApp in Phase 3. -->
                                <span class="rv-pill">Not verified yet</span>
                            </div>

                            <div class="rv-input-joined">
                                <select
                                    v-model="form.phone_country_code"
                                    class="rv-select"
                                    aria-label="Country code"
                                >
                                    <option v-for="code in countryCodes" :key="code.value" :value="code.value">
                                        {{ code.label }}
                                    </option>
                                </select>
                                <input
                                    id="rv-mobile"
                                    v-model="form.phone_number"
                                    class="rv-input"
                                    type="tel"
                                    inputmode="numeric"
                                    autocomplete="tel-national"
                                    placeholder="300 1234567"
                                />
                            </div>

                            <span class="rv-help">
                                {{ form.errors.phone_number || form.errors.phone_country_code || 'We send your matches here by WhatsApp.' }}
                            </span>
                        </div>
                    </div>

                    <span class="rv-rule rv-rule--soft"></span>

                    <div class="rv-stack" style="gap: 4px">
                        <BaseCheckbox v-model="form.is_adult">
                            I confirm I am 18 years of age or older
                        </BaseCheckbox>
                        <span v-if="form.errors.is_adult" class="rv-help" style="color: var(--rose)">
                            {{ form.errors.is_adult }}
                        </span>

                        <BaseCheckbox v-model="form.accepts_terms">
                            <span>
                                I agree to the
                                <a href="/terms" class="rv-link-brass">Terms of Service</a>
                                and
                                <a href="/privacy" class="rv-link-brass">Privacy Policy</a>
                            </span>
                        </BaseCheckbox>
                        <span v-if="form.errors.accepts_terms" class="rv-help" style="color: var(--rose)">
                            {{ form.errors.accepts_terms }}
                        </span>
                    </div>

                    <div class="rv-stack" style="gap: 20px">
                        <BaseButton type="submit" variant="primary" block :disabled="form.processing">
                            {{ form.processing ? 'Creating your account' : 'Create my free account' }}
                        </BaseButton>
                        <p class="rv-body" style="text-align: center">
                            Already have an account? <Link href="/login">Log in</Link>
                        </p>
                    </div>
                </form>
            </BaseCard>
        </section>
    </div>
</template>
