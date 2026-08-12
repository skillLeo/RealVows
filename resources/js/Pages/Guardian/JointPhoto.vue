<script setup>
import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

const props = defineProps({
    code: { type: String, required: true },
    memberName: { type: String, default: null },
    uploaded: { type: Boolean, default: false },
    status: { type: String, required: true },
});

const page = usePage();
const fileInput = ref(null);
const sending = ref(false);

function upload(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    sending.value = true;
    router.post(
        '/trusted-contact-dashboard/confirm',
        { photo: file },
        { forceFormData: true, preserveScroll: true, onFinish: () => (sending.value = false) }
    );
}
</script>

<template>
    <AppLayout title="Confirm who you are">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <template v-if="uploaded">
                <BaseCard>
                    <div class="rv-stack" style="gap: 12px">
                        <h1 class="rv-d3">{{ $t('guardian_area.confirm_done_heading') }}</h1>
                        <p class="rv-body">{{ $t('guardian_area.confirm_done_body') }}</p>
                    </div>
                </BaseCard>
            </template>

            <template v-else>
                <header class="rv-stack" style="gap: 12px">
                    <h1 class="rv-d2">{{ $t('guardian_area.confirm_heading') }}</h1>
                    <p class="rv-lead" style="max-width: 54ch">
                        {{ $t('guardian_area.confirm_lead', { name: memberName ?? 'her' }) }}
                    </p>
                </header>

                <BaseCard accent>
                    <div class="rv-stack" style="gap: 16px">
                        <span class="rv-label rv-label--brass">
                            {{ $t('guardian_area.confirm_code_label') }}
                        </span>

                        <!-- The code is the whole check: it was issued after
                             this person signed in, so a photograph holding it
                             cannot be an old picture. -->
                        <p class="rv-code">{{ code }}</p>

                        <p class="rv-help">{{ $t('guardian_area.confirm_no_id') }}</p>
                    </div>
                </BaseCard>

                <div class="rv-actions">
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        hidden
                        @change="upload"
                    >
                    <BaseButton variant="primary" :disabled="sending" @click="fileInput.click()">
                        {{ sending ? $t('guardian_area.confirm_uploading') : $t('guardian_area.confirm_upload') }}
                    </BaseButton>
                </div>

                <p v-if="page.props.errors?.photo" class="rv-help" style="color: var(--rose)">
                    {{ page.props.errors.photo }}
                </p>
            </template>
        </div>
    </AppLayout>
</template>

<style scoped>
.rv-code {
    margin: 0;
    font-family: var(--display);
    font-size: var(--fs-display-1);
    letter-spacing: .12em;
    color: var(--oxblood);
    font-variant-numeric: tabular-nums;
    font-variation-settings: 'opsz' 144, 'SOFT' 20, 'WONK' 0;
}
</style>
