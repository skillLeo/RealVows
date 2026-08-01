<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * The approved logo: RV lozenge + RealVows wordmark, brass on oxblood.
 * Client signed this off on 1 August — no further logo work.
 */
defineProps({
    href: { type: String, default: '/' },
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md'].includes(v) },
    wordmarkOnly: { type: Boolean, default: false },
});
</script>

<template>
    <Link :href="href" class="rv-logo" :class="{ 'rv-logo--sm': size === 'sm' }" aria-label="RealVows home">
        <span v-if="!wordmarkOnly" class="rv-logo__mark" aria-hidden="true">RV</span>
        <span class="rv-logo__word">RealVows</span>
    </Link>
</template>
