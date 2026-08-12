<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    ward: { type: Object, required: true },
    introductions: { type: Array, default: () => [] },
    isAppointed: { type: Boolean, default: false },
});

const page = usePage();

const form = useForm({ decision: '', gate: 'introduction', reason: '' });

function decide(matchId, decision) {
    form.decision = decision;
    form.post(`/trusted-contact-dashboard/decisions/${matchId}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout title="Introductions">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <h1 class="rv-d2">
                    {{ $t('guardian_area.dashboard_heading', { name: ward.firstName }) }}
                </h1>
                <p class="rv-lead" style="max-width: 54ch">
                    {{ $t('guardian_area.dashboard_lead') }}
                </p>
            </header>

            <p v-if="page.props.flash?.success" class="rv-flash" role="status">
                {{ page.props.flash.success }}
            </p>

            <BaseCard v-if="!introductions.length">
                <div class="rv-stack" style="gap: 8px">
                    <h2 class="rv-d3">{{ $t('guardian_area.nothing_yet') }}</h2>
                    <p class="rv-body">{{ $t('guardian_area.nothing_yet_body') }}</p>
                </div>
            </BaseCard>

            <BaseCard v-for="item in introductions" :key="item.matchId">
                <div class="rv-stack" style="gap: 20px">
                    <div class="rv-stack" style="gap: 4px">
                        <h2 class="rv-d3">{{ item.firstName }}, {{ item.age }}</h2>
                        <p class="rv-help">{{ item.city }}, {{ item.country }}</p>
                    </div>

                    <!-- ⚠️ Photographs appear only for a family trusted
                         contact. The presenter decides; this template never
                         builds a photo URL of its own. -->
                    <div v-if="item.photos?.length" class="rv-grid rv-grid--3">
                        <img
                            v-for="photo in item.photos"
                            :key="photo.id"
                            :src="photo.url"
                            alt=""
                            class="rv-guardian__photo"
                        >
                    </div>

                    <p v-else-if="item.photographsWithheld" class="rv-help">
                        {{ $t('guardian_area.withheld') }}
                    </p>

                    <p v-if="item.about" class="rv-body">{{ item.about }}</p>

                    <div class="rv-actions">
                        <BaseButton
                            variant="primary"
                            :disabled="form.processing"
                            @click="decide(item.matchId, 'approved')"
                        >
                            {{ $t('guardian_area.approve') }}
                        </BaseButton>
                        <BaseButton
                            variant="secondary"
                            :disabled="form.processing"
                            @click="decide(item.matchId, 'declined')"
                        >
                            {{ $t('guardian_area.decline') }}
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-guardian__photo {
    width: 100%;
    aspect-ratio: 3 / 4;
    object-fit: cover;
    border-radius: var(--r);
}
</style>
