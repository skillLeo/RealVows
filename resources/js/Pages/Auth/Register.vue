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
        <!--
            Left: the promise over wedding photography, veiled in oxblood.
            The image sits behind a heavy oxblood wash rather than beside the
            words — warm and ceremonial, and the headline still holds 13:1
            contrast because the wash, not the photo, is what the text sits on.
        -->
        <section class="rv-split__panel rv-split__panel--photo rv-on-dark">
            <Link href="/" class="rv-logo" aria-label="RealVows home">
                <span class="rv-logo__mark" aria-hidden="true">RV</span>
                <span class="rv-logo__word">RealVows</span>
            </Link>

            <div class="rv-split__copy rv-stack" style="gap: 32px">
                <h1 class="rv-d1" style="max-width: 20ch">{{ $t('auth.register_heading') }}</h1>

                <!--
                    ⚠️ THE FREE-PERIOD PROMISE, AT THE MOMENT SOMEBODY ACTS ON
                    IT.

                    The same sentence is in the refund policy, which is the
                    contract and which nobody reads. A promise has to reach a
                    member where they are deciding, or it is a defence we point
                    at later rather than something they relied on.

                    Silence where a price used to be reads as broken. Saying it
                    plainly reads as generous.
                -->
                <p v-if="$page.props.freePeriod" class="rv-free-note">
                    <strong>{{ $t('free_period.signup') }}</strong>
                    {{ $t('free_period.kept') }}
                </p>
                <p class="rv-lead" style="max-width: 46ch">
                    {{ $t('auth.register_free') }}
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
                    <h2 class="rv-d3">{{ $t('auth.register_submit') }}</h2>

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
                                <span class="rv-pill">{{ $t('profile.not_verified') }}</span>
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
                            {{ $t('auth.age_confirm') }}
                        </BaseCheckbox>
                        <span v-if="form.errors.is_adult" class="rv-help" style="color: var(--rose)">
                            {{ form.errors.is_adult }}
                        </span>

                        <BaseCheckbox v-model="form.accepts_terms">
                            <span>
                                {{ $t('auth.agree_to') }}
                                <a href="/terms" class="rv-link-brass">{{ $t('auth.terms') }}</a>
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
                            {{ $t('auth.have_account') }} <Link href="/login">Log in</Link>
                        </p>
                    </div>
                </form>
            </BaseCard>
        </section>
    </div>
</template>
