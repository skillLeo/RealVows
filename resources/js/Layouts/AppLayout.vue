<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import RvLogo from '@/Components/RvLogo.vue';
import BottomTabBar from '@/Components/BottomTabBar.vue';
import AccountMenu from '@/Components/AccountMenu.vue';

/**
 * Chrome for every authenticated screen.
 *
 * Desktop: a top bar carries navigation. Mobile: the top bar keeps only the
 * wordmark and the member, and the bottom tab bar carries navigation — so the
 * product reads as an installed app rather than a website.
 */
const props = defineProps({
    title: { type: String, default: null },
    // Screens with their own sticky action bar (the profile builder) manage
    // their own bottom spacing and opt out of the tab-bar padding.
    bare: { type: Boolean, default: false },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const current = computed(() => page.url.split('?')[0]);

/**
 * Staff get their own bar. Sending an administrator to the member tabs was
 * how the Profile link ended up 404ing for them — they have no profile row,
 * and a link that cannot work should not be shown in the first place.
 */
const links = computed(() =>
    user.value?.is_admin
        ? [
              { label: 'Queue', href: '/admin/verifications' },
              { label: 'Settings', href: '/admin/settings' },
              { label: 'Matches', href: '/dashboard' },
          ]
        : [
              { label: 'Matches', href: '/dashboard' },
              { label: 'Profile', href: '/profile' },
              { label: 'Packages', href: '/packages' },
              { label: 'Account', href: '/account' },
          ]
);
</script>

<template>
    <Head :title="title" />

    <a href="#rv-main" class="rv-skip">Skip to content</a>

    <div v-if="$page.props.isDemo" class="rv-demobar" role="status">
        <strong>Review build</strong>
        <span>Everyone here is invented and the sign-in buttons are open — not the live platform.</span>
    </div>

    <div class="rv-app">
        <header class="rv-app__bar">
            <div class="rv-shell rv-app__bar-inner">
                <RvLogo href="/dashboard" />

                <nav class="rv-app__nav" aria-label="Sections">
                    <Link
                        v-for="link in links"
                        :key="link.href"
                        :href="link.href"
                        class="rv-app__nav-link"
                        :class="{ 'is-active': current === link.href || current.startsWith(`${link.href}/`) }"
                        :aria-current="current === link.href ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <AccountMenu v-if="user" :user="user" />
            </div>

            <slot name="bar" />
        </header>

        <main id="rv-main" class="rv-app__main" :class="{ 'rv-has-tabbar': !bare }">
            <slot />
        </main>

        <slot name="actionbar" />

        <BottomTabBar />
    </div>
</template>

<style scoped>
.rv-app {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex;
    flex-direction: column;
    background: var(--cream);
}

.rv-app__bar {
    position: sticky;
    top: 0;
    z-index: 20;
    background: var(--oxblood-deep);
    color: var(--cream);
}

.rv-app__bar-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px 24px;
    padding-block: 20px;
}

.rv-app__nav {
    display: none;
    flex: 1 1 auto;
    justify-content: center;
    gap: 8px;
}

@media (min-width: 768px) {
    .rv-app__nav {
        display: flex;
    }
}

.rv-app__nav-link {
    min-height: var(--tap-min);
    display: inline-flex;
    align-items: center;
    padding: 0 16px;
    border-radius: var(--r);
    color: var(--on-dark);
    font-size: var(--fs-body-2);
    font-weight: 500;
    text-decoration: none;
    transition:
        background var(--dur-tap) var(--ease),
        color var(--dur-tap) var(--ease);
}

.rv-app__nav-link:hover {
    background: rgba(185, 145, 84, 0.14);
    color: var(--cream);
}

.rv-app__nav-link.is-active {
    color: var(--brass-bright);
}

.rv-app__member {
    display: flex;
    align-items: center;
    gap: 12px;
}

.rv-app__name {
    display: none;
    font-size: var(--fs-body-2);
    font-weight: 500;
    color: rgba(242, 239, 233, 0.86);
}

@media (min-width: 480px) {
    .rv-app__name {
        display: inline;
    }
}

.rv-app__avatar {
    width: var(--tap-min);
    height: var(--tap-min);
    flex: 0 0 auto;
    border: 1px solid var(--brass);
    border-radius: var(--r);
    background: var(--oxblood);
    color: var(--brass-bright);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--display);
    font-size: var(--fs-body-1);
    line-height: 1;
}

.rv-app__main {
    flex: 1 1 auto;
}
</style>
