<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseField from '@/Components/BaseField.vue';
import BaseButton from '@/Components/BaseButton.vue';
import SegmentedControl from '@/Components/SegmentedControl.vue';

const props = defineProps({
    member: { type: Object, required: true },
    balance: { type: Number, required: true },
    entries: { type: Array, required: true },
});

const page = usePage();

const form = useForm({ delta: 1, reason: 'goodwill', note: '' });

function submit() {
    form.post(`/admin/users/${props.member.id}/credits`, {
        preserveScroll: true,
        onSuccess: () => form.reset('note'),
    });
}
</script>

<template>
    <AdminLayout title="Credits">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px; max-width: 720px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">{{ member.name || member.email }}</h1>
                <span class="rv-balance">{{ balance }}</span>
                <p class="rv-small" style="margin: 0">
                    Introductions available. This figure is the sum of the ledger below. There is no
                    balance field to edit, by design.
                </p>
            </header>

            <p
                v-if="page.props.flash?.success || page.props.flash?.error"
                class="rv-flash"
                :class="{ 'rv-flash--error': !!page.props.flash?.error }"
                role="status"
            >
                {{ page.props.flash?.success || page.props.flash?.error }}
            </p>

            <BaseCard accent>
                <form class="rv-stack" style="gap: 20px" @submit.prevent="submit">
                    <span class="rv-label rv-label--brass">Adjust</span>

                    <BaseField
                        v-model="form.delta"
                        label="How many"
                        type="number"
                        inputmode="numeric"
                        help="Negative to take credits away."
                        :error="form.errors.delta"
                    />

                    <SegmentedControl
                        v-model="form.reason"
                        label="Reason"
                        :options="[
                            { value: 'goodwill', label: 'Goodwill' },
                            { value: 'admin_adjustment', label: 'Correction' },
                        ]"
                    />

                    <BaseField
                        v-model="form.note"
                        label="Why"
                        help="Required. This is the record somebody reads in a year."
                        :error="form.errors.note"
                    />

                    <BaseButton type="submit" variant="primary" :disabled="form.processing">
                        {{ form.processing ? 'Recording' : 'Record adjustment' }}
                    </BaseButton>
                </form>
            </BaseCard>

            <div class="rv-stack" style="gap: 12px">
                <span class="rv-label">Ledger</span>
                <article v-for="entry in entries" :key="entry.id" class="rv-ledger">
                    <span class="rv-ledger__delta" :class="{ 'is-negative': entry.delta < 0 }">
                        {{ entry.delta > 0 ? '+' : '' }}{{ entry.delta }}
                    </span>
                    <div class="rv-stack" style="gap: 2px; flex: 1; min-width: 0">
                        <span class="rv-strong">{{ entry.reason.replace(/_/g, ' ') }}</span>
                        <span v-if="entry.note" class="rv-small">{{ entry.note }}</span>
                        <span class="rv-small">{{ entry.at }}</span>
                    </div>
                    <span class="rv-small">balance {{ entry.balanceAfter }}</span>
                </article>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.rv-ledger {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 20px;
    border: 1px solid var(--hairline-soft);
    border-radius: var(--r);
    background: var(--card);
}

.rv-ledger__delta {
    font-family: var(--display);
    font-size: var(--fs-display-3);
    line-height: 1;
    color: var(--oxblood);
    font-variant-numeric: tabular-nums;
    min-width: 44px;
}

.rv-ledger__delta.is-negative { color: var(--rose); }
</style>
