<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import RvIcon from './RvIcon.vue';

/**
 * Mobile app chrome. Fixed to the bottom, oxblood-deep, brass active state,
 * clearing the iPhone home indicator via env(safe-area-inset-bottom).
 * Hidden at 768px and above, where the top bar takes over.
 *
 * "Matches" is the member's own delivered matches — never a directory,
 * never a browse surface.
 *
 * The tabs are the same server-built list the desktop bar renders, so the
 * two views of the product can never offer different destinations. See
 * App\Support\Navigation.
 */
const page = usePage();

const tabs = computed(() => page.props.nav ?? []);

const current = computed(() => page.url.split('?')[0]);

const isActive = (href) => current.value === href || current.value.startsWith(`${href}/`);
</script>

<template>
    <nav class="rv-tabbar" aria-label="Main">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="rv-tabbar__item"
            :class="{ 'is-active': isActive(tab.href) }"
            :aria-current="isActive(tab.href) ? 'page' : undefined"
        >
            <RvIcon :name="tab.icon" :size="22" />
            <span>{{ tab.label }}</span>
        </Link>
    </nav>
</template>
