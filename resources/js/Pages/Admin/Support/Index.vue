<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

defineProps({ tickets: { type: Array, default: () => [] } });

const page = usePage();
</script>

<template>
    <AppLayout title="Support">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 24px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Support</h1>
                <!-- ⚠️ The ordering is the policy, and it is stated so nobody
                     "fixes" it back to newest-first. -->
                <p class="rv-help">
                    Trusted-contact complaints first, then oldest. Somebody whose introductions
                    have stopped cannot wait behind a billing question.
                </p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <BaseCard v-if="!tickets.length">
                <p class="rv-body">Nothing open.</p>
            </BaseCard>

            <BaseCard v-for="ticket in tickets" :key="ticket.id" :accent="ticket.isUnfairContact">
                <div class="rv-stack" style="gap: 16px">
                    <div class="rv-stack" style="gap: 4px">
                        <span class="rv-label rv-label--brass">{{ ticket.label }}</span>
                        <p class="rv-help">{{ ticket.from }} · {{ ticket.at }}</p>
                    </div>

                    <p class="rv-body">{{ ticket.body }}</p>

                    <div class="rv-actions">
                        <!-- ⚠️ Replies go through the message thread, which is
                             where the relay prohibition is rendered. A reply box
                             here would be a second inbox without the rule on
                             it. -->
                        <BaseButton :href="`/admin/messages/${ticket.userId}`" variant="primary">
                            Reply in their thread
                        </BaseButton>

                        <BaseButton
                            variant="secondary"
                            @click="router.post(`/admin/support/${ticket.id}`, {}, { preserveScroll: true })"
                        >
                            Close
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>
