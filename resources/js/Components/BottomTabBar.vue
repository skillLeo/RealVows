<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import RvIcon from './RvIcon.vue';

/**
 * Mobile app chrome. Fixed to the bottom, oxblood-deep, brass active state,
 * clearing the iPhone home indicator via env(safe-area-inset-bottom).
 * Hidden at 768px and above, where the top bar takes over.
 *
 * Four tabs, fixed. "Matches" is the member's own delivered matches — it is
 * never a directory, never a browse surface.
 */
const page = usePage();

const tabs = [
    { label: 'Matches', icon: 'matches', href: '/dashboard' },
    { label: 'Profile', icon: 'profile', href: '/profile' },
    { label: 'Packages', icon: 'packages', href: '/packages' },
    { label: 'Account', icon: 'account', href: '/account' },
];

const current = computed(() => page.url.split('?')[0]);

const isActive = (href) => current.value === href || current.value.startsWith(`${href}/`);
</script>

<template>
    <nav class="rv-tabbar" aria-label="Main">
        <Link
            v-for="tab in tabs"
            :key="tab.href"
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
