<script setup>
import { Head, Link } from '@inertiajs/vue3';

/**
 * Chrome for the centred auth cards — log in, reset, verify.
 * The oxblood band at the top carries the wordmark, exactly as in the
 * approved 03-login screen.
 */
defineProps({
    title: { type: String, required: true },
});
</script>

<template>
    <Head :title="title" />

    <div v-if="$page.props.isDemo" class="rv-demobar" role="status">
        <strong>Review build</strong>
        <span>Everyone here is invented and the sign-in buttons are open — not the live platform.</span>
    </div>

    <div class="rv-auth">
        <div class="rv-auth__band rv-auth__band--photo">
            <Link href="/" class="rv-logo" aria-label="RealVows home">
                <span class="rv-logo__mark" aria-hidden="true">RV</span>
                <span class="rv-logo__word">RealVows</span>
            </Link>
        </div>

        <main class="rv-auth__body">
            <slot />
        </main>
    </div>
</template>

<style scoped>
.rv-auth {
    min-height: 100vh;
    min-height: 100dvh;
    background: var(--cream);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 48px;
    padding-bottom: clamp(40px, 7vw, 88px);
}

.rv-auth__band {
    width: 100%;
    height: clamp(120px, 18vw, 200px);
    background: var(--oxblood-deep);
    border-bottom: 1px solid var(--hairline);
    display: flex;
    align-items: center;
    justify-content: center;
}

.rv-auth__body {
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 32px;
    padding-inline: clamp(20px, 5vw, 64px);
}
</style>
