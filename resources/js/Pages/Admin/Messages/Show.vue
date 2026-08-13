<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    counterparty: { type: Object, required: true },
    messages: { type: Array, default: () => [] },
    relayProhibition: { type: String, required: true },
});

const page = usePage();
const form = useForm({ body: '' });

function send() {
    form.post(`/admin/messages/${props.counterparty.id}`, {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
}
</script>

<template>
    <AdminLayout title="Messages">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 24px">
            <header class="rv-stack" style="gap: 8px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">{{ counterparty.name }}</h1>
                <p class="rv-help">
                    {{ counterparty.isTrustedContact ? 'Trusted contact' : 'Member' }}
                </p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <BaseCard v-if="!messages.length">
                <p class="rv-body">Nothing has been sent yet.</p>
            </BaseCard>

            <div v-else class="rv-stack" style="gap: 12px">
                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="rv-thread__row"
                    :class="{ 'rv-thread__row--staff': message.fromStaff }"
                >
                    <p class="rv-body" style="margin: 0">{{ message.body }}</p>
                    <span class="rv-help">
                        {{ message.fromStaff ? (message.author ?? 'RealVows') : counterparty.name }}
                        · {{ message.at }}
                    </span>
                </div>
            </div>

            <BaseCard>
                <form class="rv-stack" style="gap: 16px" @submit.prevent="send">
                    <!--
                        ⚠️ THE RULE SITS WHERE THE RULE IS BROKEN.

                        "Admin never relays a message between two members" lives
                        or dies on whether the person about to do it remembers.
                        Training does not survive a busy afternoon and a member
                        asking a reasonable question, so it is here, above the
                        box, read at the moment it applies.

                        The text comes from AdminMessage::RELAY_PROHIBITION so
                        the interface and any future channel say the same thing.
                    -->
                    <p class="rv-relay-rule" role="note">
                        <strong>Never relay.</strong>
                        {{ relayProhibition }}
                    </p>

                    <textarea
                        v-model="form.body"
                        class="rv-input rv-textarea"
                        rows="4"
                        placeholder="Write to them directly"
                    />

                    <span v-if="form.errors.body" class="rv-help" style="color: var(--rose)">
                        {{ form.errors.body }}
                    </span>

                    <div class="rv-actions">
                        <BaseButton type="submit" variant="primary" :disabled="form.processing">
                            {{ form.processing ? 'Sending' : 'Send' }}
                        </BaseButton>
                    </div>
                </form>
            </BaseCard>
        </div>
    </AdminLayout>
</template>

<style scoped>
.rv-thread__row {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 16px 20px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
}

.rv-thread__row--staff {
    border-color: var(--brass);
    background: color-mix(in oklab, var(--brass) 6%, var(--card));
}

.rv-relay-rule {
    margin: 0;
    padding: 12px 16px;
    border-left: 2px solid var(--brass);
    font-size: var(--fs-body-2);
    line-height: 1.55;
    color: var(--ink);
}
</style>
