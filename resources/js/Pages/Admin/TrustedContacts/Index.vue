<script setup>
import { ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

defineProps({
    awaiting: { type: Array, default: () => [] },
    needingAppointment: { type: Array, default: () => [] },
});

const page = usePage();
const openReason = ref(null);

const form = useForm({ decision: '', reason: '' });

function decide(id, decision) {
    form.decision = decision;
    form.post(`/admin/trusted-contacts/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            openReason.value = null;
            form.reset();
        },
    });
}
</script>

<template>
    <AppLayout title="Trusted contacts">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Administration</span>
                <h1 class="rv-d2">Trusted contacts</h1>
                <p class="rv-lead" style="max-width: 56ch">
                    Compare the two people in the photograph against the relationship they claim,
                    and check the code matches.
                </p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <!-- ⚠️ THE QUEUE THAT GETS FORGOTTEN, PUT FIRST ON PURPOSE.
                 A member who told us she has nobody is waiting on US. Nothing
                 else in the product surfaces her, so if this list is below the
                 fold on a busy day she waits indefinitely with no error
                 anywhere. -->
            <BaseCard v-if="needingAppointment.length" accent>
                <div class="rv-stack" style="gap: 16px">
                    <h2 class="rv-d3">Waiting for us to appoint somebody</h2>
                    <p class="rv-body">
                        These members told us they have nobody to name. They are waiting on us,
                        not on anyone else.
                    </p>

                    <div class="rv-stack" style="gap: 8px">
                        <div
                            v-for="item in needingAppointment"
                            :key="item.id"
                            class="rv-queue__row"
                        >
                            <span class="rv-body">{{ item.memberFirstName }}</span>
                            <span class="rv-help">
                                waiting {{ item.daysWaiting }}
                                {{ item.daysWaiting === 1 ? 'day' : 'days' }},
                                since {{ item.waitingSince }}
                            </span>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <BaseCard v-if="!awaiting.length && !needingAppointment.length">
                <div class="rv-stack" style="gap: 8px">
                    <h2 class="rv-d3">Nothing waiting</h2>
                    <p class="rv-body">There are no trusted contacts to review.</p>
                </div>
            </BaseCard>

            <BaseCard v-for="item in awaiting" :key="item.id">
                <div class="rv-stack" style="gap: 20px">
                    <div class="rv-stack" style="gap: 4px">
                        <h2 class="rv-d3">{{ item.name }}</h2>
                        <p class="rv-help">
                            {{ item.relationship }} of {{ item.memberFirstName }}
                            · submitted {{ item.submittedAt }}
                        </p>
                    </div>

                    <!-- The code they were asked to hold. Checking it is the
                         whole point of the photograph. -->
                    <div class="rv-stack" style="gap: 4px">
                        <span class="rv-label rv-label--brass">Code they were given</span>
                        <p class="rv-queue__code">{{ item.code }}</p>
                    </div>

                    <img v-if="item.photoUrl" :src="item.photoUrl" alt="" class="rv-queue__photo">

                    <div v-if="item.flags.length" class="rv-stack" style="gap: 4px">
                        <span class="rv-label rv-label--brass">Worth a closer look</span>
                        <p v-for="flag in item.flags" :key="flag" class="rv-help">{{ flag }}</p>
                    </div>

                    <div v-if="openReason === item.id" class="rv-stack" style="gap: 8px">
                        <label class="rv-label" :for="`reason-${item.id}`">
                            Why, so she knows what to do next
                        </label>
                        <textarea
                            :id="`reason-${item.id}`"
                            v-model="form.reason"
                            class="rv-input rv-textarea"
                            rows="3"
                        />
                        <span v-if="form.errors.reason" class="rv-help" style="color: var(--rose)">
                            {{ form.errors.reason }}
                        </span>
                    </div>

                    <div class="rv-actions">
                        <BaseButton
                            variant="primary"
                            :disabled="form.processing"
                            @click="decide(item.id, 'verified')"
                        >
                            Confirm this one
                        </BaseButton>

                        <BaseButton
                            v-if="openReason !== item.id"
                            variant="secondary"
                            @click="openReason = item.id"
                        >
                            Not confirmed
                        </BaseButton>

                        <BaseButton
                            v-else
                            variant="secondary"
                            :disabled="form.processing"
                            @click="decide(item.id, 'rejected')"
                        >
                            Send this reason
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-queue__row {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 8px 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--hairline);
}

.rv-queue__code {
    margin: 0;
    font-family: var(--display);
    font-size: var(--fs-display-3);
    letter-spacing: .12em;
    color: var(--oxblood);
    font-variant-numeric: tabular-nums;
}

.rv-queue__photo {
    width: 100%;
    max-width: 420px;
    border-radius: var(--r);
    border: 1px solid var(--hairline);
}
</style>
