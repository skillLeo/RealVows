<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BaseCard from '@/Components/BaseCard.vue';
import BaseButton from '@/Components/BaseButton.vue';

defineProps({
    packages: { type: Array, required: true },
    credits: { type: Number, default: 0 },
    completeness: { type: Number, default: 0 },
});
</script>

<template>
    <AppLayout title="Packages">
        <div class="rv-shell rv-section--app rv-stack" style="gap: 32px">
            <header class="rv-stack" style="gap: 12px">
                <span class="rv-label">Introductions</span>
                <h1 class="rv-d2">{{ $t('packages.heading') }}</h1>
                <span class="rv-underline" aria-hidden="true" />
            </header>

            <!-- No pressure anywhere. Somebody not ready to pay should leave
                 feeling their profile is safe, not feeling pushed. -->
            <BaseCard accent>
                <div class="rv-stack" style="gap: 12px">
                    <span class="rv-label rv-label--brass">{{ $t('packages.reassurance_label') }}</span>
                    <p class="rv-body" style="margin: 0">
                        {{ $t('packages.reassurance') }}
                    </p>
                </div>
            </BaseCard>

            <div class="rv-rail">
                <article
                    v-for="pack in packages"
                    :key="pack.key"
                    class="rv-package rv-pressable"
                    :class="{ 'rv-package--featured': pack.key === 'triple' }"
                >
                    <span v-if="pack.badge" class="rv-match__lozenge">{{ pack.badge }}</span>

                    <div class="rv-stack" style="gap: 4px">
                        <span class="rv-package__price">{{ pack.price }}</span>
                        <span class="rv-small">
                            {{ pack.credits }} introduction<template v-if="pack.credits !== 1">s</template>
                        </span>
                    </div>

                    <p class="rv-body" style="margin: 0; flex: 1">{{ pack.label }}</p>

                    <BaseButton
                        :href="`/checkout/${pack.key}`"
                        :variant="pack.key === 'triple' ? 'primary' : 'secondary'"
                        block
                    >
                        {{ $t('packages.choose') }}
                    </BaseButton>
                </article>
            </div>

            <p class="rv-small">
                You have {{ credits }} introduction<template v-if="credits !== 1">s</template> to open right now.
            </p>
        </div>
    </AppLayout>
</template>
