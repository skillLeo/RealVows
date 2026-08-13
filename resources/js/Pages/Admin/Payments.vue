<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';

defineProps({
    purchases: { type: Object, required: true },
    filters: { type: Object, required: true },
    mode: { type: String, required: true },
});

const page = usePage();

function requery(id) {
    router.post(`/admin/payments/${id}/requery`, {}, { preserveScroll: true });
}

function voidIt(id) {
    router.post(`/admin/payments/${id}/void`, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Payments">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <!--
                ⚠️ Loud on purpose. Nobody should ever mistake a test
                transaction for a real one — or, far worse, the other way
                round.
            -->
            <p v-if="mode === 'sandbox'" class="rv-demobar" style="border-radius: 8px">
                <strong>Sandbox</strong>
                <span>These are test transactions. No real money has moved.</span>
            </p>

            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Payments</h1>
            </header>

            <p
                v-if="page.props.flash?.success || page.props.flash?.error"
                class="rv-flash"
                :class="{ 'rv-flash--error': !!page.props.flash?.error }"
                role="status"
            >
                {{ page.props.flash?.success || page.props.flash?.error }}
            </p>

            <nav class="rv-tabs" aria-label="Payment status">
                <Link
                    v-for="tab in [
                        { key: '', label: 'All' },
                        { key: 'completed', label: 'Completed' },
                        { key: 'pending_3ds', label: 'In flight' },
                        { key: 'failed', label: 'Failed' },
                    ]"
                    :key="tab.key"
                    :href="`/admin/payments${tab.key ? `?status=${tab.key}` : ''}`"
                    class="rv-tab"
                    :class="{ 'is-active': filters.status === tab.key }"
                >
                    {{ tab.label }}
                </Link>
            </nav>

            <BaseCard v-if="!purchases.data.length">
                <p class="rv-body" style="margin: 0">No transactions yet.</p>
            </BaseCard>

            <div v-else class="rv-stack" style="gap: 12px">
                <article v-for="p in purchases.data" :key="p.id" class="rv-row">
                    <div class="rv-stack" style="gap: 2px; flex: 1; min-width: 0">
                        <span class="rv-strong">{{ p.reference }} · {{ p.amount }}</span>
                        <span class="rv-small">{{ p.email }} · {{ p.createdAt }}</span>
                    </div>

                    <span class="rv-match__lozenge">{{ p.status }}</span>

                    <div class="rv-actions">
                        <button
                            v-if="p.canRequery"
                            type="button"
                            class="rv-btn rv-btn--ghost"
                            @click="requery(p.id)"
                        >
                            Ask the gateway
                        </button>
                        <button v-if="p.canVoid" type="button" class="rv-btn rv-btn--ghost" @click="voidIt(p.id)">
                            Void
                        </button>
                    </div>
                </article>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.rv-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px;
    padding: 16px 24px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
}
</style>
