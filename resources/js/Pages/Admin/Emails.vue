<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';
import BaseField from '@/Components/BaseField.vue';

/**
 * ⚠️ THE INDEX IS IN THE ADMIN SHELL. THE PREVIEWS THEMSELVES ARE NOT, AND
 * THAT SPLIT IS THE WHOLE POINT OF THIS FILE.
 *
 * This page used to be a hand-built HTML string returned straight from the
 * controller — no layout, no sidebar, inline styles, a bare white page in the
 * middle of the admin area. It looked broken because it was outside everything.
 *
 * The reason it was raw was sound and applies to the wrong half. A preview of
 * an email must render in a page carrying NONE of the product's CSS, or the
 * app's stylesheet leaks into it and the preview flatters the email: you see a
 * layout Gmail will never produce. So `show` stays a bare document, and the
 * links below deliberately leave the Inertia app.
 *
 * But the index is a list of links and a send form. There is no email being
 * previewed here to contaminate, so there is no reason for it to sit outside
 * the shell.
 */
defineProps({
    emails: { type: Array, required: true },
});

const form = useForm({ key: '', to: '' });

function send() {
    form.post('/admin/emails/send', { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Email previews" eyebrow="Operations">
        <div class="rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <h1 class="rv-d2">Email previews</h1>
                <p class="rv-lead" style="max-width: 60ch">
                    A browser is not a mail client. These render correctly in Chrome no matter
                    what is wrong with them. Use the form below to check what actually arrives
                    in Gmail, Apple Mail and Outlook.
                </p>
            </header>

            <BaseCard>
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label">The catalogue</span>

                    <p v-if="!emails.length" class="rv-help">
                        No mailables are registered for preview.
                    </p>

                    <!--
                        ⚠️ PLAIN ANCHORS WITH target, NOT <Link>.

                        These open documents that are deliberately not Inertia
                        pages. An Inertia visit would fetch one, receive HTML
                        with no page object, and force a full reload — leaving
                        the reviewer stranded on a bare preview with no way back
                        but the browser button. A new tab keeps the admin area
                        where they left it.
                    -->
                    <ul class="rv-emails">
                        <li v-for="email in emails" :key="email.key">
                            <a :href="`/admin/emails/${email.key}`" target="_blank" rel="noopener">
                                {{ email.label }}
                            </a>
                            <a
                                :href="`/admin/emails/${email.key}?format=text`"
                                target="_blank"
                                rel="noopener"
                                class="rv-emails__text"
                            >plain text</a>
                        </li>
                    </ul>
                </div>
            </BaseCard>

            <BaseCard>
                <form class="rv-stack" style="gap: 20px" @submit.prevent="send">
                    <div class="rv-stack" style="gap: 8px">
                        <h2 class="rv-d3">Send a real one</h2>
                        <p class="rv-help">
                            Goes through whatever is set in Configuration → Email. While the
                            driver is still <code>log</code> it is written to
                            <code>storage/logs</code> and nothing leaves the server.
                        </p>
                    </div>

                    <BaseField label="Which one" :error="form.errors.key">
                        <select v-model="form.key" class="rv-input" required>
                            <option value="" disabled>Choose an email</option>
                            <option v-for="email in emails" :key="email.key" :value="email.key">
                                {{ email.label }}
                            </option>
                        </select>
                    </BaseField>

                    <BaseField label="Send it to" :error="form.errors.to">
                        <input
                            v-model="form.to"
                            type="email"
                            class="rv-input"
                            placeholder="you@example.com"
                            required
                        >
                    </BaseField>

                    <div class="rv-actions">
                        <BaseButton type="submit" variant="primary" :disabled="form.processing">
                            {{ form.processing ? 'Sending' : 'Send it' }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.rv-emails {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 2px;
}

.rv-emails li {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 16px;
    padding: 10px 12px;
    border-radius: var(--r-sm, 8px);
}

.rv-emails li:hover {
    background: color-mix(in oklab, var(--oxblood) 6%, transparent);
}

.rv-emails a {
    color: var(--oxblood);
    text-decoration: none;
}

.rv-emails a:hover {
    text-decoration: underline;
}

/* The text part is the one nobody looks at and the one spam filters weigh, so
   it stays reachable — quietly, as a secondary action rather than a twin. */
.rv-emails__text {
    flex: none;
    font-size: 13px;
    color: var(--ink-soft) !important;
}
</style>
