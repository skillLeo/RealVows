<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

defineProps({
    waiting: { type: Array, required: true },
});

const page = usePage();
const notes = ref({});
const busy = ref(null);

function record(id) {
    busy.value = id;
    router.post(`/admin/introductions/${id}`, { note: notes.value[id] ?? '' }, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
}
</script>

<template>
    <AdminLayout title="Introductions to make">
        <div class="rv-stack" style="gap: 24px">
            <header class="rv-stack" style="gap: 8px">
                <h1 class="rv-d2">Introductions to make</h1>

                <!--
                    These are the members with no family to carry an
                    introduction for them. The two people must not be put in
                    touch with each other directly, so somebody here does it and
                    is named on the record afterwards.
                -->
                <p class="rv-body">
                    Each of these members has a trusted contact appointed by RealVows rather than
                    one from their own family. They will not receive these details themselves.
                    Make the introduction, then record it below so your name is on it.
                </p>
            </header>

            <div v-if="page.props.flash?.success" class="rv-note rv-note--good">
                {{ page.props.flash.success }}
            </div>
            <div v-if="page.props.flash?.error" class="rv-note rv-note--bad">
                {{ page.props.flash.error }}
            </div>

            <p v-if="!waiting.length" class="rv-help">There is nothing waiting.</p>

            <BaseCard v-for="item in waiting" :key="item.id" class="rv-stack" style="gap: 16px">
                <div class="rv-stack" style="gap: 4px">
                    <span class="rv-label rv-label--brass">Mutual interest since {{ item.since }}</span>
                    <p class="rv-help">Compatibility {{ Math.round(item.score) }}</p>
                </div>

                <div class="rv-grid rv-grid--2" style="gap: 20px">
                    <div class="rv-stack" style="gap: 6px">
                        <span class="rv-label">Her</span>
                        <p class="rv-body">{{ item.her.name }}</p>
                        <p class="rv-help">
                            <a :href="`mailto:${item.her.email}`">{{ item.her.email }}</a>
                        </p>
                        <p v-if="item.her.phone" class="rv-help">
                            <a :href="`tel:${item.her.phone.replace(/\s+/g, '')}`">{{ item.her.phone }}</a>
                        </p>
                    </div>

                    <div class="rv-stack" style="gap: 6px">
                        <span class="rv-label">Him</span>
                        <p class="rv-body">{{ item.him.name }}</p>
                        <p class="rv-help">
                            <a :href="`mailto:${item.him.email}`">{{ item.him.email }}</a>
                        </p>
                        <p v-if="item.him.phone" class="rv-help">
                            <a :href="`tel:${item.him.phone.replace(/\s+/g, '')}`">{{ item.him.phone }}</a>
                        </p>
                    </div>
                </div>

                <label class="rv-field">
                    <span class="rv-label">Note, if there is anything worth keeping</span>
                    <textarea v-model="notes[item.id]" class="rv-input" rows="2"></textarea>
                </label>

                <div>
                    <BaseButton :disabled="busy === item.id" @click="record(item.id)">
                        I have made this introduction
                    </BaseButton>
                </div>
            </BaseCard>
        </div>
    </AdminLayout>
</template>
