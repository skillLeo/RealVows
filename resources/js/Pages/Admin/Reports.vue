<script setup>
import { ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

defineProps({
    reports: { type: Object, required: true },
    status: { type: String, required: true },
    counts: { type: Object, required: true },
});

const page = usePage();
const notes = ref({});

function resolve(id, status) {
    router.post(`/admin/reports/${id}`, { status, reviewer_note: notes.value[id] ?? '' }, {
        preserveScroll: true,
    });
}

const TABS = [
    { key: 'open', label: 'Open' },
    { key: 'reviewed', label: 'Reviewed' },
    { key: 'actioned', label: 'Actioned' },
    { key: 'dismissed', label: 'Dismissed' },
];
</script>

<template>
    <AdminLayout title="Reports">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Concerns raised</h1>
                <span class="rv-underline" aria-hidden="true" />
                <p class="rv-lead" style="max-width: 60ch">
                    Members can report someone they were introduced to. The person reported is
                    never told, and not that a report exists, not who raised it, and not what you
                    decide.
                </p>
            </header>

            <p
                v-if="page.props.flash?.success"
                class="rv-flash"
                role="status"
            >
                {{ page.props.flash.success }}
            </p>

            <nav class="rv-tabs" aria-label="Report status">
                <Link
                    v-for="tab in TABS"
                    :key="tab.key"
                    :href="`/admin/reports?status=${tab.key}`"
                    class="rv-tab"
                    :class="{ 'is-active': status === tab.key }"
                    :aria-current="status === tab.key ? 'page' : undefined"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="counts[tab.key] > 0"
                        class="rv-tab__count"
                    >{{ counts[tab.key] }}</span>
                    <span v-else class="rv-tab__count rv-tab__count--done">—</span>
                </Link>
            </nav>

            <BaseCard v-if="!reports.data.length">
                <p class="rv-body" style="margin: 0">
                    Nothing here. That is the good outcome.
                </p>
            </BaseCard>

            <div v-else class="rv-stack" style="gap: 16px">
                <article v-for="report in reports.data" :key="report.id" class="rv-report">
                    <div class="rv-stack" style="gap: 8px">
                        <span class="rv-strong">{{ report.reason }}</span>
                        <span class="rv-small">
                            About <strong>{{ report.subject }}</strong> · raised by
                            {{ report.reporter }} · {{ report.raisedAt }}
                        </span>
                        <p v-if="report.note" class="rv-body" style="margin: 8px 0 0">
                            “{{ report.note }}”
                        </p>
                    </div>

                    <div v-if="status === 'open'" class="rv-stack" style="gap: 12px; margin-top: 16px">
                        <input
                            v-model="notes[report.id]"
                            class="rv-input"
                            placeholder="What you decided, and why"
                        />
                        <div class="rv-actions">
                            <BaseButton variant="primary" @click="resolve(report.id, 'actioned')">
                                Actioned
                            </BaseButton>
                            <BaseButton variant="secondary" @click="resolve(report.id, 'reviewed')">
                                Reviewed, no action
                            </BaseButton>
                            <BaseButton variant="ghost" @click="resolve(report.id, 'dismissed')">
                                Dismiss
                            </BaseButton>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.rv-report {
    padding: 24px;
    border: 1px solid var(--hairline);
    border-radius: var(--r);
    background: var(--card);
}
</style>
