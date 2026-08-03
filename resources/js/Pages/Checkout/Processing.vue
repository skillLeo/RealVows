<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';

/**
 * The waiting room.
 *
 * Skeletons rather than a spinner: a spinner says "waiting", a skeleton says
 * "arriving". And a hard timeout, because a page that hangs forever while
 * somebody's money is in flight is the worst screen in the product.
 */
const props = defineProps({
    order: { type: String, required: true },
    reference: { type: String, required: true },
    challengeHtml: { type: String, default: null },
    resultUrl: { type: String, required: true },
});

const host = ref(null);
let timer = null;

onMounted(() => {
    // The bank's own challenge form, submitted from inside a designed page
    // rather than as a flash of raw markup.
    if (props.challengeHtml && host.value) {
        host.value.innerHTML = props.challengeHtml;
        host.value.querySelector('form')?.submit();
    }

    // Ninety seconds is generous for a 3DS challenge. After that, the result
    // screen will re-query and give a real answer either way.
    timer = setTimeout(() => {
        window.location.href = props.resultUrl;
    }, 90000);
});

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="rv-processing">
        <div class="rv-stack" style="gap: 24px; max-width: 420px; text-align: center">
            <span class="rv-label rv-label--brass">{{ reference }}</span>
            <h1 class="rv-d3">{{ $t('checkout.confirming') }}</h1>
            <p class="rv-body">
                {{ $t('checkout.do_not_close') }}
            </p>

            <div class="rv-stack" style="gap: 12px" aria-hidden="true">
                <span class="rv-skeleton" style="height: 14px; width: 80%; margin: 0 auto" />
                <span class="rv-skeleton" style="height: 14px; width: 60%; margin: 0 auto" />
                <span class="rv-skeleton" style="height: 14px; width: 70%; margin: 0 auto" />
            </div>

            <div ref="host" hidden />
        </div>
    </div>
</template>

<style scoped>
.rv-processing {
    display: grid;
    place-items: center;
    min-height: 100vh;
    min-height: 100dvh;
    padding: 32px;
    background: var(--cream);
}
</style>
