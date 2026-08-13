<script setup>
import { computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useAdminShell } from '@/shell';
import RvIcon from '@/Components/RvIcon.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseCheckbox from '@/Components/BaseCheckbox.vue';
import BaseField from '@/Components/BaseField.vue';
import PasswordField from '@/Components/PasswordField.vue';
import VerifiedBadge from '@/Components/VerifiedBadge.vue';

const props = defineProps({
    hub: { type: Array, default: () => [] },
    account: { type: Object, required: true },
    countryCodes: { type: Array, required: true },
    notifications: { type: Object, default: () => ({}) },
    trustedContact: { type: Object, default: null },
});

const page = usePage();

/*
 * ⚠️ THE ONE SCREEN BOTH A MEMBER AND STAFF OPEN, so it is the one screen that
 * has to choose its own chrome. Clicking "Your account" in the admin sidebar
 * used to drop a reviewer into the member layout — sidebar gone, a top bar
 * appearing with four links, and the account dropdown repeating them again.
 */
const Shell = useAdminShell();

/**
 * The administration shortcuts come from the same server-built navigation
 * every other surface renders, so this card cannot offer a door the tab bar
 * knows is shut.
 *
 * It used to be gated on an `isAdmin` flag sent by the controller — a role
 * check standing in for a permission check, which showed a reviewer a
 * Settings button that 404'd. Same defect as the nav bar had, hiding one
 * layer down in a page rather than in the chrome.
 */
const adminLinks = computed(() =>
    (page.props.nav ?? []).filter((link) => link.href.startsWith('/admin'))
);

const notifyForm = useForm({
    email_matches: props.notifications?.emailMatches ?? true,
    whatsapp_matches: props.notifications?.whatsappMatches ?? false,
});

function saveNotifications() {
    notifyForm.post('/account/notifications', { preserveScroll: true });
}

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
    <component :is="Shell ? AdminLayout : AppLayout" title="Your account">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Your account</span>
                <h1 class="rv-d2">Settings</h1>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <!-- ── The hub ──────────────────────────────────────────────────
                 ⚠️ ABOVE THE FORMS, BECAUSE IT IS WHY MOST PEOPLE CAME.

                 The bottom bar is four tabs and stays four. Everything else the
                 product grew — the full introduction history, the trusted
                 contact, receipts, help — lives here, and /introductions had no
                 route in from any member screen at all.

                 Each row carries its own state, because "Needed before we can
                 introduce you" is the answer to the question the tap was going
                 to ask. Rows come from the server so one cannot appear for
                 somebody who would get a 404 opening it. -->
            <!-- ⚠️ Structurally this cannot be empty today — two groups are
                 unconditionally visible in MemberHub. The branch is here
                 because that is a guarantee made in another file, and because
                 a future policy that hid every row would otherwise leave this
                 page silently smaller with nothing to explain it. -->
            <p v-if="!hub.length" class="rv-help">
                {{ $t('account.hub_empty') }}
            </p>

            <nav
                v-for="group in hub"
                :key="group.key"
                class="rv-stack"
                style="gap: 10px"
                :aria-label="group.label"
            >
                <span class="rv-label">{{ group.label }}</span>

                <ul class="rv-hub">
                    <li v-for="item in group.items" :key="item.key">
                        <Link :href="item.href" class="rv-hub__row">
                            <RvIcon :name="item.icon" :size="18" class="rv-hub__icon" />

                            <span class="rv-hub__text">
                                <span class="rv-hub__label">{{ item.label }}</span>
                                <!-- Absent, not empty. A row with a blank line
                                     reserved under it reads as a value that
                                     failed to load. -->
                                <span v-if="item.status" class="rv-hub__status">{{ item.status }}</span>
                            </span>

                            <RvIcon name="arrow-right" :size="16" class="rv-hub__go" />
                        </Link>
                    </li>
                </ul>
            </nav>

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
                        {{ $t('account.your_email_is_how') }}
                    </p>
                </div>
            </BaseCard>

            <BaseCard>
                <form class="rv-stack" style="gap: 24px" @submit.prevent="saveContact">
                    <div class="rv-stack" style="gap: 8px">
                        <h2 class="rv-d3">Mobile number</h2>
                        <p class="rv-body">{{ $t('account.this_is_where_your') }}</p>
                    </div>

                    <div class="rv-field" :class="{ 'rv-field--error': !!contact.errors.phone_number }">
                        <div class="rv-field__head">
                            <label for="rv-account-phone" class="rv-label">Mobile number</label>
                            <VerifiedBadge v-if="account.phoneVerified" label="Verified" />
                            <span v-else class="rv-pill">{{ $t('profile.not_verified') }}</span>
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
                    <h2 class="rv-d3">{{ $t('account.change_your_password') }}</h2>

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

            <BaseCard>
                <form class="rv-stack" style="gap: 20px" @submit.prevent="saveNotifications">
                    <span class="rv-label">{{ $t('account.notifications') }}</span>
                    <span class="rv-underline" aria-hidden="true" />

                    <BaseCheckbox v-model="notifyForm.email_matches">
                        {{ $t('account.email_matches') }}
                    </BaseCheckbox>

                    <div class="rv-stack" style="gap: 4px">
                        <BaseCheckbox v-model="notifyForm.whatsapp_matches">
                            {{ $t('account.whatsapp_matches') }}
                        </BaseCheckbox>
                        <!-- Honest about not being connected yet. A switch
                             that silently does nothing is worse than one that
                             says when it will start working. -->
                        <span class="rv-help">
                            {{ $t('account.whatsapp_note') }}
                        </span>
                    </div>

                    <div class="rv-stack" style="gap: 4px">
                        <span class="rv-strong">{{ $t('account.receipts') }}</span>
                        <span class="rv-help">
                            {{ $t('account.receipts_note') }}
                        </span>
                    </div>

                    <BaseButton type="submit" variant="secondary" :disabled="notifyForm.processing">
                        {{ notifyForm.processing ? 'Saving' : 'Save preferences' }}
                    </BaseButton>
                </form>
            </BaseCard>

            <!-- ⚠️ ABSENT, NOT DISABLED, WHEN THE POLICY DOES NOT OFFER IT.
                 The requirement is unset by design; rendering a greyed-out card
                 would advertise a system nobody has switched on. -->
            <BaseCard v-if="trustedContact">
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-label rv-label--brass">{{ $t('trusted_contact.heading') }}</span>
                    <p class="rv-body">
                        {{ trustedContact.state.message ?? $t('trusted_contact.lead') }}
                    </p>
                    <div class="rv-actions">
                        <BaseButton href="/trusted-contact" variant="secondary">
                            {{ $t('trusted_contact.heading') }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>

            <!-- ⚠️ Support is on the account page rather than the tab bar, same
                 argument as the trusted-contact card: four tabs, never five.
                 Without a link it is reachable only by typing the URL, which is
                 how a support route goes unused by the people who need it. -->
            <BaseCard>
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-label rv-label--brass">{{ $t('support.heading') }}</span>
                    <p class="rv-body">{{ $t('support.lead') }}</p>
                    <div class="rv-actions">
                        <BaseButton href="/support" variant="secondary">
                            {{ $t('support.heading') }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>

            <BaseCard v-if="adminLinks.length" accent>
                <div class="rv-stack" style="gap: 16px">
                    <span class="rv-label rv-label--brass">Administration</span>
                    <div class="rv-actions">
                        <BaseButton
                            v-for="link in adminLinks"
                            :key="link.key"
                            :href="link.href"
                            variant="secondary"
                        >
                            {{ link.label }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>

            <BaseCard quiet>
                <div class="rv-account__row">
                    <div class="rv-stack" style="gap: 6px">
                        <span class="rv-strong">{{ $t('account.signed_in_on_this') }}</span>
                        <span class="rv-small">{{ $t('account.your_profile_stays_exactly') }}</span>
                    </div>
                    <BaseButton variant="ghost" @click="logout">Log out</BaseButton>
                </div>
            </BaseCard>

            <p class="rv-small">
                <Link href="/terms">{{ $t('auth.terms') }}</Link> ·
                <Link href="/privacy">Privacy Policy</Link> ·
                <Link href="/community-guidelines">Community Guidelines</Link>
            </p>
        </div>
    </component>
</template>

<style scoped>

/* ── The hub ─────────────────────────────────────────────────────────────
   Rows, not cards. A card per destination would give six boxes equal visual
   weight to the account forms below them, and this is an index — the reader is
   scanning for one line, not comparing six things. */
.rv-hub {
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
    overflow: hidden;
}

/* One hairline between rows, none at the ends — the container already has its
   own border and a doubled edge is the commonest way a list like this looks
   slightly wrong without anybody being able to say why. */
.rv-hub li + li { border-block-start: 1px solid var(--hairline); }

.rv-hub__row {
    display: flex;
    align-items: center;
    gap: 14px;
    /* 56px tall: comfortably past the 44px touch floor, because these are the
       primary targets on the screen and thumbs are imprecise on a bus. */
    min-height: 56px;
    padding: 12px 16px;
    color: inherit;
    text-decoration: none;
    transition: background-color 140ms var(--ease);
}

.rv-hub__row:hover,
.rv-hub__row:focus-visible {
    background: color-mix(in oklab, var(--oxblood) 5%, var(--card));
}

.rv-hub__icon {
    flex: none;
    color: var(--brass-ink);
}

.rv-hub__text {
    display: grid;
    gap: 2px;
    min-width: 0;
    flex: 1;
}

.rv-hub__label {
    font-weight: 600;
    line-height: 1.3;
}

/* ⚠️ The status is the point of the row, so it is legible rather than faint —
   ink-soft, not a 40% tint. It is the sentence that saves the tap. */
.rv-hub__status {
    font-size: 13px;
    line-height: 1.35;
    color: var(--ink-soft);
}

/* Quiet: an affordance, not an action. It says "this goes somewhere" and
   should not compete with the label. */
.rv-hub__go {
    flex: none;
    color: var(--ink-soft);
    opacity: .55;
}

.rv-account__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px 20px;
}
</style>
